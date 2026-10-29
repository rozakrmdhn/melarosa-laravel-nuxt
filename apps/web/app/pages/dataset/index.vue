<script setup lang="ts">
import { useGeoportalLayers, type GeoportalLayerItem } from "~/composables/useGeoportalLayers";

useSeoMeta({
  title: "Katalog Dataset Geospasial | Melarosa Bojonegoro",
  description: "Direktori resmi data spasial Simpul Jaringan Informasi Geospasial Nasional (JIGN) Kabupaten Bojonegoro.",
});

const {
  groupedLayers,
  categories,
  filteredGrouped,
  totalFilteredCount,
  searchQuery,
  selectedCategory,
  loading,
  refresh,
} = useGeoportalLayers();

const groupedList = computed(() => {
  return Object.entries(filteredGrouped.value).map(([category, layers]) => ({
    category,
    layers,
  }));
});

const selectedLayerForModal = ref<GeoportalLayerItem | null>(null);
const isModalOpen = ref(false);

function openMetadataModal(layer: GeoportalLayerItem) {
  selectedLayerForModal.value = layer;
  isModalOpen.value = true;
}

function handleOpenInMap(layer: GeoportalLayerItem) {
  const query = encodeURIComponent(layer.title || layer.name || "");
  navigateTo(`/maps?search=${query}`);
}
</script>

<template>
  <PublicPageTemplate
    title="Katalog Dataset Geospasial"
    description="Pusat direktori dataset geospasial resmi Simpul Jaringan Informasi Geospasial Nasional (JIGN) Kabupaten Bojonegoro."
    icon="i-lucide-database"
    container-size="default"
  >
    <template #actions>
      <UButton
        to="/maps"
        icon="i-lucide-map"
        color="primary"
        variant="solid"
        size="sm"
        class="rounded-xl font-medium"
      >
        Buka di Peta Spasial
      </UButton>
    </template>

    <div class="py-6 space-y-6">
      <!-- Bilah Pencarian & Kategori Filter -->
      <div class="flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
        <!-- Input Pencarian -->
        <div class="relative flex-1 max-w-md">
          <UIcon
            name="i-lucide-search"
            class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-slate-400 pointer-events-none"
          />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari dataset, OPD, atau kata kunci..."
            class="w-full bg-slate-50 dark:bg-white/[0.04] border border-slate-200 dark:border-white/10 rounded-xl pl-10 pr-9 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-hidden focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all min-h-[44px]"
          />
          <button
            v-if="searchQuery"
            type="button"
            class="absolute right-2.5 top-1/2 -translate-y-1/2 size-6 rounded-full bg-slate-200 dark:bg-white/10 text-slate-600 dark:text-slate-300 flex items-center justify-center hover:bg-slate-300 dark:hover:bg-white/20 transition-all"
            aria-label="Bersihkan pencarian"
            @click="searchQuery = ''"
          >
            <UIcon name="i-lucide-x" class="size-3.5" />
          </button>
        </div>

        <!-- Filter Pill Kategori -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
          <button
            type="button"
            class="px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-all cursor-pointer min-h-[36px]"
            :class="selectedCategory === 'ALL' || !selectedCategory
              ? 'bg-blue-600 text-white shadow-xs'
              : 'bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/10'"
            @click="selectedCategory = 'ALL'"
          >
            Semua ({{ totalFilteredCount }})
          </button>
          <button
            v-for="cat in categories"
            :key="cat.name"
            type="button"
            class="px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-all cursor-pointer min-h-[36px]"
            :class="selectedCategory === cat.name
              ? 'bg-blue-600 text-white shadow-xs'
              : 'bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/10'"
            @click="selectedCategory = cat.name"
          >
            {{ cat.name }} ({{ cat.count }})
          </button>
        </div>
      </div>

      <!-- State: Memuat Data -->
      <div v-if="loading" class="py-16 text-center">
        <UIcon name="i-lucide-loader-2" class="size-8 animate-spin text-blue-600 dark:text-blue-400 mx-auto mb-3" />
        <p class="text-sm text-slate-600 dark:text-slate-400 font-medium">
          Memuat direktori dataset Geoportal JIGN...
        </p>
      </div>

      <!-- State: Kosong / Tidak Ditemukan -->
      <div
        v-else-if="groupedList.length === 0"
        class="rounded-2xl border border-dashed border-slate-300 dark:border-white/10 p-10 text-center bg-slate-50/50 dark:bg-white/[0.02]"
      >
        <UIcon name="i-lucide-database" class="size-10 text-slate-400 mx-auto mb-3" />
        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100 mb-1">
          Tidak Ada Dataset Ditemukan
        </h3>
        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-sm mx-auto mb-4">
          <template v-if="searchQuery">
            Tidak ada dataset yang sesuai dengan kata kunci "{{ searchQuery }}". Silakan coba kata kunci lain.
          </template>
          <template v-else>
            Belum ada dataset yang tersedia untuk kategori yang dipilih.
          </template>
        </p>
        <UButton
          color="neutral"
          variant="soft"
          size="sm"
          class="rounded-xl font-medium"
          @click="searchQuery = ''; selectedCategory = 'ALL'"
        >
          Reset Filter Pencarian
        </UButton>
      </div>

      <!-- Daftar Grup Kategori & Kartu Dataset -->
      <div v-else class="space-y-8">
        <section
          v-for="group in groupedList"
          :key="group.category"
          class="space-y-3"
        >
          <!-- Judul Kategori -->
          <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-white/10">
            <h2 class="text-base font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
              <span class="size-2 rounded-full bg-blue-600 dark:bg-blue-400" />
              {{ group.category }}
            </h2>
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 tabular-nums">
              {{ group.layers.length }} dataset
            </span>
          </div>

          <!-- Grid Kartu Dataset -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
              v-for="layer in group.layers"
              :key="layer.id"
              class="rounded-2xl border border-slate-200/90 dark:border-white/10 bg-white dark:bg-[#0c1424] p-4 sm:p-5 flex flex-col justify-between hover:border-blue-300 dark:hover:border-blue-700/60 transition-all shadow-xs hover:shadow-md"
            >
              <div class="space-y-2.5">
                <!-- Baris Atas: Badge Protokol & OPD -->
                <div class="flex items-center justify-between gap-2">
                  <span
                    class="text-[10px] font-bold font-mono tracking-wider uppercase px-2 py-0.5 rounded-md bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200/60 dark:border-blue-800/40"
                  >
                    {{ layer.protocol || "WMS" }}
                  </span>
                  <span
                    v-if="layer.opd_name || layer.attribution || layer.agency"
                    class="text-[11px] font-medium text-slate-500 dark:text-slate-400 truncate max-w-[180px]"
                    :title="layer.opd_name || layer.attribution || layer.agency || ''"
                  >
                    {{ layer.opd_name || layer.attribution || layer.agency }}
                  </span>
                </div>

                <!-- Judul Dataset -->
                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100 line-clamp-2 leading-snug">
                  {{ layer.title || layer.name }}
                </h3>

                <!-- Deskripsi Singkat -->
                <p class="text-xs text-slate-600 dark:text-slate-400 line-clamp-3 leading-relaxed">
                  {{ layer.abstract || layer.description || "Dataset spasial resmi Simpul Jaringan Informasi Geospasial Kabupaten Bojonegoro." }}
                </p>
              </div>

              <!-- Tombol Aksi Bawah -->
              <div class="pt-4 mt-3 border-t border-slate-100 dark:border-white/5 flex items-center justify-between gap-2">
                <button
                  type="button"
                  class="text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer py-1.5"
                  @click="openMetadataModal(layer)"
                >
                  Lihat Detail
                </button>

                <UButton
                  color="primary"
                  variant="soft"
                  size="xs"
                  icon="i-lucide-map"
                  class="rounded-lg font-medium"
                  @click="handleOpenInMap(layer)"
                >
                  Buka di Peta
                </UButton>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>

    <!-- Modal Detail Metadata Dataset -->
    <UModal v-model:open="isModalOpen">
      <template #content>
        <div v-if="selectedLayerForModal" class="p-6 space-y-4">
          <div class="flex items-start justify-between gap-3">
            <div>
              <span class="text-[10px] font-bold font-mono uppercase px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200/60 dark:border-blue-800/40">
                {{ selectedLayerForModal.protocol || "WMS" }}
              </span>
              <h3 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 mt-2">
                {{ selectedLayerForModal.title || selectedLayerForModal.name }}
              </h3>
            </div>
            <UButton
              color="neutral"
              variant="ghost"
              icon="i-lucide-x"
              size="sm"
              class="rounded-full"
              @click="isModalOpen = false"
            />
          </div>

          <div class="space-y-3 text-xs sm:text-sm">
            <div>
              <span class="font-semibold text-slate-700 dark:text-slate-300 block mb-1">Deskripsi</span>
              <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                {{ selectedLayerForModal.abstract || selectedLayerForModal.description || "Tidak ada deskripsi rinci untuk layer ini." }}
              </p>
            </div>

            <div class="grid grid-cols-2 gap-3 p-3 rounded-xl bg-slate-50 dark:bg-white/[0.03] border border-slate-200/80 dark:border-white/10 text-xs">
              <div>
                <span class="text-slate-500 dark:text-slate-400 block">Instansi Pengelola:</span>
                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ selectedLayerForModal.opd_name || selectedLayerForModal.attribution || selectedLayerForModal.agency || "BAPPEDA Bojonegoro" }}</span>
              </div>
              <div>
                <span class="text-slate-500 dark:text-slate-400 block">Kategori Tematik:</span>
                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ selectedLayerForModal.category || "Infrastruktur & Wilayah" }}</span>
              </div>
              <div class="col-span-2">
                <span class="text-slate-500 dark:text-slate-400 block">Layer Teknis:</span>
                <span class="font-mono text-slate-800 dark:text-slate-200 break-all">{{ selectedLayerForModal.name || selectedLayerForModal.layer_name }}</span>
              </div>
            </div>
          </div>

          <div class="pt-3 border-t border-slate-200 dark:border-white/10 flex justify-end gap-2">
            <UButton
              color="neutral"
              variant="soft"
              class="rounded-xl font-medium"
              @click="isModalOpen = false"
            >
              Tutup
            </UButton>
            <UButton
              color="primary"
              icon="i-lucide-map"
              class="rounded-xl font-medium"
              @click="handleOpenInMap(selectedLayerForModal); isModalOpen = false"
            >
              Lihat di Peta Spasial
            </UButton>
          </div>
        </div>
      </template>
    </UModal>
  </PublicPageTemplate>
</template>

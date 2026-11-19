<template>
  <div class="min-h-screen bg-[#F7F8F5] dark:bg-[#070b14] text-slate-900 dark:text-slate-100 flex flex-col font-sans transition-colors duration-300">
    <main class="flex-1 pt-6 sm:pt-10 pb-24">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- 1. Editorial Masthead & Simpul JIGN Identity -->
        <header class="pb-8 border-b border-slate-200/80 dark:border-white/[0.08]">
          <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <div class="inline-flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-[#2B5B9E] dark:bg-sky-400" />
              <span class="text-xs font-semibold uppercase tracking-[0.14em] text-[#2B5B9E] dark:text-sky-400">
                Simpul Jaringan Geospasial · Kab. Bojonegoro
              </span>
            </div>

            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] text-xs font-medium text-slate-700 dark:text-slate-300">
              <span class="w-2 h-2 rounded-full" :class="statusIndicatorClass" />
              <span>{{ statusLabel }}</span>
              <template v-if="connectionStatus?.response_time_ms">
                <span class="text-slate-300 dark:text-slate-700">·</span>
                <span class="tabular-nums text-slate-500 dark:text-slate-400">{{ connectionStatus.response_time_ms }} ms</span>
              </template>
            </div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 lg:gap-8 items-end">
            <div class="lg:col-span-8">
              <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-slate-900 dark:text-white tracking-tight leading-[1.15] mb-2.5">
                Katalog Dataset Geospasial
              </h1>
              <p class="text-sm sm:text-[15px] text-slate-600 dark:text-slate-400 leading-relaxed max-w-2xl">
                Direktori resmi layer peta dan metadata spasial Pemerintah Kabupaten Bojonegoro standar OGC (WMS, WFS, CSW) dan KUGI BIG.
              </p>
            </div>

            <div class="lg:col-span-4 flex flex-wrap lg:justify-end items-center gap-2.5">
              <button
                type="button"
                :disabled="loading || checkingStatus"
                class="min-h-[44px] inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] hover:bg-slate-50 dark:hover:bg-[#132033] text-slate-800 dark:text-slate-200 text-xs font-semibold transition-colors cursor-pointer disabled:opacity-50"
                @click="handleSyncNode"
              >
                <UIcon
                  name="i-lucide-refresh-cw"
                  class="w-4 h-4 text-[#2B5B9E] dark:text-sky-400"
                  :class="{ 'animate-spin': loading || checkingStatus }"
                />
                <span>Segarkan Simpul</span>
              </button>

              <NuxtLink
                to="/maps"
                class="min-h-[44px] inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#2B5B9E] hover:bg-[#224A82] text-white text-xs font-semibold transition-colors shadow-xs"
              >
                <UIcon name="i-lucide-map" class="w-4 h-4" />
                <span>Buka Peta WebGIS</span>
              </NuxtLink>
            </div>
          </div>

          <!-- 2. Simpul JIGN Telemetry Strip -->
          <div class="mt-7 rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-4 sm:p-5">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 sm:gap-6">
              <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                  Dataset Terindeks
                </p>
                <div class="flex items-baseline gap-2">
                  <p class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white tabular-nums tracking-tight">
                    {{ allLayers.length }}
                  </p>
                  <span class="text-xs text-slate-500 dark:text-slate-400">layer aktif</span>
                </div>
              </div>

              <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                  Kategori Tematik
                </p>
                <div class="flex items-baseline gap-2">
                  <p class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white tabular-nums tracking-tight">
                    {{ categories.length }}
                  </p>
                  <span class="text-xs text-slate-500 dark:text-slate-400">rumpun urusan</span>
                </div>
              </div>

              <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                  Wali Data OPD
                </p>
                <div class="flex items-baseline gap-2">
                  <p class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white tabular-nums tracking-tight">
                    {{ uniqueOpdCount }}
                  </p>
                  <span class="text-xs text-slate-500 dark:text-slate-400">perangkat daerah</span>
                </div>
              </div>

              <div class="col-span-2 lg:col-span-1 flex flex-col justify-between pt-4 lg:pt-0 border-t lg:border-t-0 lg:border-l border-slate-200/80 dark:border-white/[0.08] lg:pl-6">
                <div>
                  <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                    Simpul Katalog Induk
                  </p>
                  <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate">
                    geoportal.bojonegorokab.go.id
                  </p>
                </div>
                <div class="mt-2 flex items-center justify-between gap-2">
                  <span class="text-[11px] text-slate-500 dark:text-slate-400 tabular-nums">
                    Sinkron: {{ formatRelativeSync(connectionStatus?.last_synced_at) }}
                  </span>
                  <a
                    href="https://geoportal.bojonegorokab.go.id/csw/?service=CSW&version=2.0.2&request=GetCapabilities"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#2B5B9E] dark:text-sky-400 hover:underline"
                  >
                    <span>CSW XML</span>
                    <UIcon name="i-lucide-arrow-up-right" class="w-3.5 h-3.5" />
                  </a>
                </div>
              </div>
            </div>
          </div>
        </header>

        <!-- 3. Search & Category Filter Workspace -->
        <section aria-label="Pencarian dan Filter Dataset" class="py-7">
          <div class="flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
            <div class="relative flex-1 max-w-2xl">
              <UIcon
                name="i-lucide-search"
                class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none"
              />
              <input
                v-model="searchQuery"
                type="search"
                placeholder="Cari judul dataset, wali data OPD, atau nama layer..."
                class="w-full min-h-[46px] pl-11 pr-11 py-2.5 bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] rounded-xl text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#2B5B9E]/30 focus:border-[#2B5B9E] transition-all"
              />
              <button
                v-if="searchQuery"
                type="button"
                aria-label="Hapus kata kunci pencarian"
                class="w-11 h-11 absolute right-1 top-1/2 -translate-y-1/2 inline-flex items-center justify-center text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 cursor-pointer"
                @click="searchQuery = ''"
              >
                <UIcon name="i-lucide-x" class="w-4 h-4" />
              </button>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
              <div class="relative flex-1 sm:flex-initial">
                <UIcon
                  name="i-lucide-building-2"
                  class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"
                />
                <select
                  v-model="selectedOpd"
                  aria-label="Filter berdasarkan Wali Data OPD"
                  class="w-full sm:w-auto min-h-[46px] pl-10 pr-9 py-2.5 bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-[#2B5B9E]/30 appearance-none cursor-pointer"
                >
                  <option :value="null">Semua Wali Data ({{ uniqueOpdList.length }})</option>
                  <option v-for="opd in uniqueOpdList" :key="opd" :value="opd">
                    {{ opd }}
                  </option>
                </select>
                <UIcon
                  name="i-lucide-chevron-down"
                  class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                />
              </div>

              <button
                v-if="hasActiveFilter"
                type="button"
                class="min-h-[46px] inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-slate-200/70 dark:bg-[#132033] text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-[#1b2d47] transition-colors cursor-pointer"
                @click="resetAllFilters"
              >
                <UIcon name="i-lucide-rotate-ccw" class="w-3.5 h-3.5" />
                <span>Reset</span>
              </button>
            </div>
          </div>

          <!-- Category Pills -->
          <div
            v-if="categories.length > 0"
            class="mt-4 flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar"
            role="tablist"
            aria-label="Kategori Tematik Dataset"
          >
            <button
              type="button"
              role="tab"
              :aria-selected="!selectedCategory || selectedCategory === 'ALL'"
              class="min-h-[40px] inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer"
              :class="
                !selectedCategory || selectedCategory === 'ALL'
                  ? 'bg-[#2B5B9E] text-white shadow-xs'
                  : 'bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
              "
              @click="selectedCategory = 'ALL'"
            >
              <span>Semua Kategori</span>
              <span
                class="px-2 py-0.5 rounded-full text-[11px] tabular-nums"
                :class="
                  !selectedCategory || selectedCategory === 'ALL'
                    ? 'bg-white/20 text-white'
                    : 'bg-slate-100 dark:bg-white/[0.06] text-slate-600 dark:text-slate-400'
                "
              >
                {{ allLayers.length }}
              </span>
            </button>

            <button
              v-for="cat in categories"
              :key="cat.name"
              type="button"
              role="tab"
              :aria-selected="selectedCategory === cat.name"
              class="min-h-[40px] inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer"
              :class="
                selectedCategory === cat.name
                  ? 'bg-[#2B5B9E] text-white shadow-xs'
                  : 'bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
              "
              @click="selectedCategory = selectedCategory === cat.name ? 'ALL' : cat.name"
            >
              <span>{{ cat.name }}</span>
              <span
                class="px-2 py-0.5 rounded-full text-[11px] tabular-nums"
                :class="
                  selectedCategory === cat.name
                    ? 'bg-white/20 text-white'
                    : 'bg-slate-100 dark:bg-white/[0.06] text-slate-600 dark:text-slate-400'
                "
              >
                {{ cat.count }}
              </span>
            </button>
          </div>
        </section>

        <!-- 4. Loading Skeleton -->
        <div v-if="loading && allLayers.length === 0" class="space-y-8 py-4">
          <div v-for="i in 2" :key="i" class="space-y-4">
            <div class="h-6 w-56 bg-slate-200/70 dark:bg-[#0c1522] rounded-lg animate-pulse" />
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
              <div
                v-for="j in 3"
                :key="j"
                class="h-60 rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-6 animate-pulse flex flex-col justify-between"
              >
                <div class="space-y-3">
                  <div class="h-4 w-28 bg-slate-200 dark:bg-slate-800 rounded" />
                  <div class="h-6 w-4/5 bg-slate-200 dark:bg-slate-800 rounded" />
                  <div class="h-4 w-full bg-slate-100 dark:bg-slate-800/70 rounded" />
                </div>
                <div class="h-10 w-full bg-slate-100 dark:bg-slate-800/70 rounded-xl" />
              </div>
            </div>
          </div>
        </div>

        <!-- 5. Error State -->
        <div
          v-else-if="error && allLayers.length === 0"
          class="my-8 rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-8 sm:p-10 text-center max-w-xl mx-auto"
        >
          <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center mx-auto mb-4">
            <UIcon name="i-lucide-wifi-off" class="w-6 h-6" />
          </div>
          <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-2">
            Koneksi ke Simpul JIGN Terkendala
          </h2>
          <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed mb-6">
            {{ error }}
          </p>
          <button
            type="button"
            class="min-h-[44px] inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#2B5B9E] hover:bg-[#224A82] text-white text-xs font-semibold transition-colors cursor-pointer"
            @click="handleSyncNode"
          >
            <UIcon name="i-lucide-refresh-cw" class="w-4 h-4" />
            <span>Muat Ulang Katalog</span>
          </button>
        </div>

        <!-- 6. Empty Search/Filter Result -->
        <div
          v-else-if="displayedTotalCount === 0"
          class="my-8 rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-10 text-center max-w-xl mx-auto"
        >
          <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-white/[0.05] text-slate-500 dark:text-slate-400 flex items-center justify-center mx-auto mb-4">
            <UIcon name="i-lucide-search-x" class="w-6 h-6" />
          </div>
          <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-2">
            Dataset Tidak Ditemukan
          </h2>
          <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed mb-6">
            Tidak ada dataset yang cocok dengan kata kunci atau filter wali data yang dipilih.
          </p>
          <button
            type="button"
            class="min-h-[44px] inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#2B5B9E] hover:bg-[#224A82] text-white text-xs font-semibold transition-colors cursor-pointer"
            @click="resetAllFilters"
          >
            <UIcon name="i-lucide-rotate-ccw" class="w-4 h-4" />
            <span>Tampilkan Semua ({{ allLayers.length }})</span>
          </button>
        </div>

        <!-- 7. Grouped Dataset Directory -->
        <div v-else class="space-y-10">
          <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400">
            <p>
              Menampilkan
              <strong class="font-semibold text-slate-900 dark:text-white tabular-nums">{{ displayedTotalCount }}</strong>
              dari
              <span class="tabular-nums">{{ allLayers.length }}</span>
              dataset
            </p>
            <p class="hidden sm:block">
              Pilih kartu untuk melihat metadata ISO 19139, BBOX, dan skema atribut WFS.
            </p>
          </div>

          <section
            v-for="(layers, categoryName) in displayedGrouped"
            :key="categoryName"
            class="space-y-4"
          >
            <div class="flex items-baseline justify-between gap-4 pb-2.5 border-b border-slate-200/80 dark:border-white/[0.08]">
              <div class="flex items-center gap-2.5">
                <span class="w-2 h-2 rounded-full bg-[#2B5B9E] dark:bg-sky-400" />
                <h2 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white tracking-tight">
                  {{ categoryName }}
                </h2>
              </div>
              <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 tabular-nums">
                {{ layers.length }} dataset
              </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
              <article
                v-for="layer in layers"
                :key="layer.id"
                class="group rounded-2xl bg-white dark:bg-[#0c1522] border border-slate-200/80 dark:border-white/[0.08] p-5 sm:p-6 flex flex-col justify-between transition-all duration-200 hover:border-[#2B5B9E]/40 hover:shadow-md dark:hover:bg-[#101c2e]"
              >
                <div>
                  <DatasetMapThumbnail
                    :layer="layer"
                    class="mb-4"
                    @preview="openDatasetMinimap"
                  />

                  <div class="flex items-center justify-between gap-3 mb-2">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-[#2B5B9E] dark:text-sky-400 truncate">
                      {{ layer.opd_name || layer.attribution || "Pemkab Bojonegoro" }}
                    </span>
                    <span class="shrink-0 px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase tracking-wider bg-slate-100 dark:bg-white/[0.06] text-slate-600 dark:text-slate-300">
                      {{ (layer.protocol || "WMS").toUpperCase() }}
                    </span>
                  </div>

                  <h3
                    class="text-base font-bold text-slate-900 dark:text-white leading-snug mb-2 line-clamp-2 group-hover:text-[#2B5B9E] dark:group-hover:text-sky-400 transition-colors cursor-pointer"
                    @click="openMetadataInspector(layer, 'summary')"
                  >
                    {{ layer.name }}
                  </h3>

                  <p class="text-[13px] text-slate-600 dark:text-slate-400 leading-relaxed line-clamp-2 mb-4">
                    {{ getCleanAbstract(layer) }}
                  </p>
                </div>

                <div class="pt-3.5 border-t border-slate-100 dark:border-white/[0.06] space-y-3.5">
                  <div class="flex items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400 min-w-0">
                    <UIcon name="i-lucide-layers" class="w-3.5 h-3.5 shrink-0 text-slate-400" />
                    <span class="font-mono truncate" :title="layer.layer_name">
                      {{ formatShortLayerName(layer.layer_name) }}
                    </span>
                  </div>

                  <div class="flex items-center gap-2">
                    <button
                      type="button"
                      class="flex-1 min-h-[44px] inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-[#2B5B9E] text-slate-800 hover:text-white dark:bg-white/[0.06] dark:hover:bg-[#2B5B9E] dark:text-slate-200 dark:hover:text-white text-xs font-semibold transition-colors cursor-pointer"
                      @click="openMetadataInspector(layer, 'summary')"
                    >
                      <UIcon name="i-lucide-file-search-2" class="w-4 h-4 shrink-0" />
                      <span>Lihat Metadata</span>
                    </button>

                    <button
                      type="button"
                      aria-label="Pratinjau minimap dataset"
                      title="Pratinjau Minimap"
                      class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-xl bg-slate-100 dark:bg-white/[0.04] hover:bg-[#2B5B9E] text-slate-700 dark:text-slate-300 hover:text-white transition-colors cursor-pointer"
                      @click="openDatasetMinimap(layer)"
                    >
                      <UIcon name="i-lucide-map" class="w-4 h-4" />
                    </button>

                    <NuxtLink
                      :to="buildWebgisDatasetRoute(layer)"
                      aria-label="Visualisasikan layer di Peta WebGIS"
                      title="Buka di Peta WebGIS"
                      class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-xl bg-slate-100 dark:bg-white/[0.04] hover:bg-[#2B5B9E]/10 text-slate-600 dark:text-slate-300 hover:text-[#2B5B9E] dark:hover:text-sky-400 transition-colors"
                      @click="prepareWebgisDatasetHandoff(layer)"
                    >
                      <UIcon name="i-lucide-map-pin" class="w-4 h-4" />
                    </NuxtLink>
                  </div>
                </div>
              </article>
            </div>
          </section>
        </div>

      </div>
    </main>

    <!-- 8A. Desktop Dialog Inspector -->
    <UModal
      v-if="!isMobileScreen"
      v-model:open="isModalOpen"
      :title="activeLayer?.name || 'Inspeksi Metadata Dataset JIGN'"
      description="Metadata geospasial lengkap Simpul JIGN Kabupaten Bojonegoro standar OGC, ISO 19139, Dublin Core, dan KUGI BIG."
      :ui="{
        overlay: 'z-[100] bg-slate-950/65 backdrop-blur-xs',
        content: 'z-[110] w-[calc(100vw-3rem)] max-w-5xl max-h-[88vh] bg-[#F7F8F5] dark:bg-[#070b14] text-slate-900 dark:text-slate-100 rounded-2xl shadow-2xl flex flex-col overflow-hidden ring-1 ring-slate-900/10 dark:ring-white/10 p-0 sm:p-0'
      }"
    >
      <template #content>
        <MetadataInspectorContent
          v-if="activeLayer"
          v-model:active-tab="activeTab"
          :layer="activeLayer"
          :loading-inspector="loadingInspector"
          :full-metadata="fullMetadata"
          :attributes-data="attributesData"
          :kugi-feature-info="kugiFeatureInfo"
          :is-mobile="false"
          @close="isModalOpen = false"
          @refresh="loadFullLayerInspection(activeLayer, true)"
          @open-minimap="switchToMinimapFromMetadata(activeLayer)"
        />
      </template>
    </UModal>

    <!-- 8B. Mobile Bottom Sheet Inspector -->
    <UDrawer
      v-else
      v-model:open="isModalOpen"
      direction="bottom"
      :title="activeLayer?.name || 'Inspeksi Metadata Dataset JIGN'"
      description="Metadata geospasial lengkap Simpul JIGN Kabupaten Bojonegoro."
      :ui="{
        overlay: 'z-[100] bg-slate-950/65 backdrop-blur-xs',
        content: 'z-[110] max-h-[92dvh] bg-white dark:bg-[#0c1522] text-slate-900 dark:text-slate-100 rounded-t-3xl shadow-2xl flex flex-col overflow-hidden ring-1 ring-slate-900/10 dark:ring-white/10 p-0',
        handle: 'w-10 h-1 rounded-full bg-slate-300 dark:bg-slate-600 mx-auto mt-2.5 mb-1 shrink-0'
      }"
    >
      <template #content>
        <MetadataInspectorContent
          v-if="activeLayer"
          v-model:active-tab="activeTab"
          :layer="activeLayer"
          :loading-inspector="loadingInspector"
          :full-metadata="fullMetadata"
          :attributes-data="attributesData"
          :kugi-feature-info="kugiFeatureInfo"
          :is-mobile="true"
          @close="isModalOpen = false"
          @refresh="loadFullLayerInspection(activeLayer, true)"
          @open-minimap="switchToMinimapFromMetadata(activeLayer)"
        />
      </template>
    </UDrawer>

    <!-- 9. Minimap Preview (Desktop Dialog & Mobile Bottom Sheet via MapLibre GL JS) -->
    <DatasetMinimapViewer
      v-model:open="isMinimapOpen"
      :layer="minimapLayer"
      @inspect-metadata="(l) => switchToMetadataFromMinimap(l)"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import type { GeoportalLayerItem } from "~/composables/useGeoportalLayers";
import MetadataInspectorContent, { type InspectorTab } from "~/components/dataset/MetadataInspectorContent.vue";
import DatasetMinimapViewer from "~/components/dataset/DatasetMinimapViewer.vue";
import DatasetMapThumbnail from "~/components/dataset/DatasetMapThumbnail.vue";

usePublicSeo({
  title: "Katalog Dataset & Metadata JIGN",
  description:
    "Katalog resmi dataset dan metadata geospasial Simpul Jaringan Informasi Geospasial Nasional (JIGN) Pemerintah Kabupaten Bojonegoro berstandar OGC WMS, WFS, CSW, ISO 19139, dan KUGI BIG.",
  path: "/dataset",
  jsonLd: {
    "@context": "https://schema.org",
    "@type": "DataCatalog",
    name: "Katalog Dataset Geospasial JIGN Kabupaten Bojonegoro",
    url: "https://melarosa.saggaserv.my.id/dataset",
    inLanguage: "id-ID",
    publisher: {
      "@type": "GovernmentOrganization",
      name: "Pemerintah Kabupaten Bojonegoro",
    },
  },
});

const isMobileScreen = ref(false);
const updateViewportMode = () => {
  if (typeof window !== "undefined") {
    isMobileScreen.value = window.innerWidth < 768;
  }
};

onMounted(() => {
  updateViewportMode();
  window.addEventListener("resize", updateViewportMode, { passive: true });
});

onBeforeUnmount(() => {
  if (typeof window !== "undefined") {
    window.removeEventListener("resize", updateViewportMode);
  }
});

const {
  allLayers,
  categories,
  filteredGrouped,
  searchQuery,
  selectedCategory,
  loading,
  error,
  refresh,
  connectionStatus,
  checkingStatus,
  checkConnection,
  fetchLayerMetadata,
  fetchLayerAttributes,
} = useGeoportalLayers();

const selectedOpd = ref<string | null>(null);

const uniqueOpdList = computed(() => {
  const set = new Set<string>();
  for (const item of allLayers.value) {
    const opd = (item.opd_name || item.attribution || "").trim();
    if (opd) set.add(opd);
  }
  return Array.from(set).sort((a, b) => a.localeCompare(b, "id"));
});

const uniqueOpdCount = computed(() => uniqueOpdList.value.length || 1);

const displayedGrouped = computed(() => {
  const base = filteredGrouped.value;
  if (!selectedOpd.value) return base;

  const result: Record<string, GeoportalLayerItem[]> = {};
  for (const [cat, items] of Object.entries(base)) {
    const matched = items.filter((l) => (l.opd_name || l.attribution || "").trim() === selectedOpd.value);
    if (matched.length > 0) {
      result[cat] = matched;
    }
  }
  return result;
});

const displayedTotalCount = computed(() =>
  Object.values(displayedGrouped.value).reduce((sum, arr) => sum + arr.length, 0)
);

const hasActiveFilter = computed(() =>
  Boolean(
    searchQuery.value.trim() ||
      (selectedCategory.value && selectedCategory.value !== "ALL") ||
      selectedOpd.value
  )
);

const resetAllFilters = () => {
  searchQuery.value = "";
  selectedCategory.value = "ALL";
  selectedOpd.value = null;
};

const statusIndicatorClass = computed(() => {
  const st = connectionStatus.value?.status;
  if (st === "online") return "bg-emerald-500";
  if (st === "fallback") return "bg-amber-500";
  return "bg-slate-400";
});

const statusLabel = computed(() => {
  const st = connectionStatus.value?.status;
  if (st === "online") return "Simpul JIGN Online";
  if (st === "fallback") return "Katalog Lokal Terindeks";
  if (checkingStatus.value) return "Memeriksa Simpul...";
  return "Katalog Tersedia";
});

const handleSyncNode = async () => {
  await Promise.all([refresh(), checkConnection(true)]);
};

const formatRelativeSync = (isoDate?: string | null) => {
  if (!isoDate) return "Hari ini";
  try {
    const d = new Date(isoDate);
    return d.toLocaleDateString("id-ID", {
      day: "2-digit",
      month: "short",
      year: "numeric",
    });
  } catch {
    return "Terbaru";
  }
};

const formatShortLayerName = (rawName?: string) => {
  if (!rawName) return "-";
  const parts = rawName.split(":");
  return parts[parts.length - 1] || rawName;
};

const getCleanAbstract = (layer: GeoportalLayerItem) => {
  const desc = (layer.description || "").trim();
  if (desc && desc.toLowerCase() !== "none" && desc !== "-") {
    return desc;
  }
  return `Dataset spasial tematik ${layer.name} yang dikelola oleh ${layer.opd_name || "Pemerintah Kabupaten Bojonegoro"} dalam jaringan JIGN.`;
};

const isModalOpen = ref(false);
const activeLayer = ref<GeoportalLayerItem | null>(null);
const activeTab = ref<InspectorTab>("summary");
const loadingInspector = ref(false);

const fullMetadata = ref<any>(null);
const attributesData = ref<any>(null);
const kugiFeatureInfo = ref<any>(null);

const isMinimapOpen = ref(false);
const minimapLayer = ref<GeoportalLayerItem | null>(null);
const pendingWebgisDataset = usePendingWebgisDataset();

const prepareWebgisDatasetHandoff = (layer: GeoportalLayerItem) => {
  pendingWebgisDataset.value = layer;
};

const openDatasetMinimap = (layer: GeoportalLayerItem) => {
  minimapLayer.value = layer;
  isMinimapOpen.value = true;
};

const switchToMinimapFromMetadata = (layer: GeoportalLayerItem) => {
  const enrichedLayer: GeoportalLayerItem = {
    ...layer,
    pycsw: fullMetadata.value?.pycsw || layer.pycsw || null,
  };
  isModalOpen.value = false;
  setTimeout(() => {
    openDatasetMinimap(enrichedLayer);
  }, 120);
};

const switchToMetadataFromMinimap = (layer: GeoportalLayerItem) => {
  isMinimapOpen.value = false;
  setTimeout(() => {
    openMetadataInspector(layer, "summary");
  }, 120);
};

const openMetadataInspector = async (layer: GeoportalLayerItem, initialTab: InspectorTab = "summary") => {
  activeLayer.value = layer;
  activeTab.value = initialTab;
  isModalOpen.value = true;

  await loadFullLayerInspection(layer, false);
};

const loadFullLayerInspection = async (layer: GeoportalLayerItem, forceRefresh = false) => {
  loadingInspector.value = true;
  if (forceRefresh) {
    fullMetadata.value = null;
    attributesData.value = null;
    kugiFeatureInfo.value = null;
  }

  try {
    const [metaRes, attrRes] = await Promise.all([
      fetchLayerMetadata(layer.id, forceRefresh).catch(() => null),
      fetchLayerAttributes(layer.id, forceRefresh).catch(() => null),
    ]);

    fullMetadata.value = metaRes?.data || metaRes || null;
    attributesData.value = attrRes?.data || attrRes || null;

    const fcode = attributesData.value?.detected_fcode;
    if (fcode) {
      try {
        const upperCode = String(fcode).trim().toUpperCase();
        const kugiRes = await $http<{ ok: boolean; code: string; data: any[] }>("kugi/feature-type", {
          query: { code: upperCode },
        });
        const items = Array.isArray(kugiRes?.data) ? kugiRes.data : [];
        if (items.length > 0) {
          const first = items[0];
          const attrMap: Record<string, string> = {};
          for (const item of items) {
            if (item?.faCode && item?.ptDefinition) {
              attrMap[String(item.faCode).toUpperCase()] = String(item.ptDefinition).trim();
            }
          }
          kugiFeatureInfo.value = {
            code: upperCode,
            typeName: first.typeName || undefined,
            aliases: first.aliases || undefined,
            definition: first.definition || undefined,
            producer: first.fcProducer || undefined,
            attributes: attrMap,
          };
        }
      } catch {
        // Fallback silently if KUGI endpoint is not matched
      }
    }
  } finally {
    loadingInspector.value = false;
  }
};
</script>

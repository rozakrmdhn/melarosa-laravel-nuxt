<template>
  <div class="min-h-screen bg-[#F7F8F5] dark:bg-[#070b14] text-slate-900 dark:text-slate-100 flex flex-col font-sans transition-colors duration-300">
    <main class="flex-1 pt-8 sm:pt-12 pb-24">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- 1. Editorial Masthead & Simpul JIGN Identity -->
        <header class="pb-10 lg:pb-12 border-b border-[#E4EAE3] dark:border-white/[0.07]">
          <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
            <div class="inline-flex items-center gap-2.5">
              <span class="w-2 h-2 rounded-full bg-emerald-600 dark:bg-emerald-400"></span>
              <span class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-400">
                Simpul Jaringan Informasi Geospasial Nasional (JIGN) · Kabupaten Bojonegoro
              </span>
            </div>

            <!-- Live JIGN Connection Pill -->
            <div class="inline-flex items-center gap-2.5 px-3.5 py-1.5 rounded-full bg-[#EAEFE9] dark:bg-[#0c1522] text-xs font-medium text-slate-700 dark:text-slate-300">
              <span
                class="w-2 h-2 rounded-full"
                :class="statusIndicatorClass"
              ></span>
              <span>{{ statusLabel }}</span>
              <template v-if="connectionStatus?.response_time_ms">
                <span class="text-slate-300 dark:text-slate-700">·</span>
                <span class="tabular-nums text-slate-500 dark:text-slate-400">{{ connectionStatus.response_time_ms }} ms</span>
              </template>
            </div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-end">
            <div class="lg:col-span-8">
              <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-slate-900 dark:text-white tracking-tight leading-[1.12] mb-4">
                Katalog Dataset &amp; Metadata Geospasial Daerah
              </h1>
              <p class="text-base sm:text-lg text-slate-600 dark:text-slate-400 leading-relaxed max-w-3xl">
                Direktori resmi layanan data spasial Pemerintah Kabupaten Bojonegoro yang terintegrasi dengan standar
                Open Geospatial Consortium (OGC WMS, WFS, CSW), skema metadata ISO 19115/19139, Dublin Core pyCSW 2.6.0,
                serta Kamus Unsur Geospasial Indonesia (KUGI v5) Badan Informasi Geospasial.
              </p>
            </div>

            <div class="lg:col-span-4 flex flex-wrap lg:justify-end items-center gap-3">
              <button
                type="button"
                :disabled="loading || checkingStatus"
                class="min-h-[44px] inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#EAEFE9] dark:bg-[#0c1522] hover:bg-[#dfe6de] dark:hover:bg-[#132033] text-slate-800 dark:text-slate-200 text-xs font-semibold transition-colors cursor-pointer disabled:opacity-50"
                @click="handleSyncNode"
              >
                <UIcon
                  name="i-lucide-refresh-cw"
                  class="w-4 h-4 text-emerald-700 dark:text-emerald-400"
                  :class="{ 'animate-spin': loading || checkingStatus }"
                />
                <span>Segarkan Simpul</span>
              </button>

              <NuxtLink
                to="/maps"
                class="min-h-[44px] inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500 text-white text-xs font-semibold transition-colors"
              >
                <UIcon name="i-lucide-map" class="w-4 h-4" />
                <span>Eksplorasi di Peta WebGIS</span>
              </NuxtLink>
            </div>
          </div>

          <!-- 2. Simpul JIGN Telemetry & Architecture Strip (Tonal Surface, No Heavy Borders) -->
          <div class="mt-8 rounded-2xl bg-[#EAEFE9]/80 dark:bg-[#0c1522] p-5 sm:p-6">
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-6">
              <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                  Dataset Terindeks
                </p>
                <p class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white tabular-nums tracking-tight">
                  {{ allLayers.length }}
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                  Layer geospasial aktif
                </p>
              </div>

              <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                  Kategori Tematik
                </p>
                <p class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white tabular-nums tracking-tight">
                  {{ categories.length }}
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                  Rumpun urusan daerah
                </p>
              </div>

              <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                  Wali Data OPD
                </p>
                <p class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white tabular-nums tracking-tight">
                  {{ uniqueOpdCount }}
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                  Perangkat daerah produsen
                </p>
              </div>

              <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                  Standar Metadata
                </p>
                <p class="text-base sm:text-lg font-bold text-emerald-800 dark:text-emerald-400 tracking-tight mt-1">
                  ISO 19139 &amp; KUGI v5
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                  OGC WMS · WFS · CSW 2.0.2
                </p>
              </div>

              <div class="col-span-2 sm:col-span-4 lg:col-span-1 flex flex-col justify-between pt-4 lg:pt-0 border-t lg:border-t-0 lg:border-l border-[#DCE3DA] dark:border-white/[0.07] lg:pl-6">
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
                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-400 hover:underline"
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
        <section aria-label="Pencarian dan Filter Dataset" class="py-8">
          <div class="flex flex-col lg:flex-row gap-4 items-stretch lg:items-center justify-between">
            <!-- Search Input -->
            <div class="relative flex-1 max-w-2xl">
              <UIcon
                name="i-lucide-search"
                class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none"
              />
              <input
                v-model="searchQuery"
                type="search"
                placeholder="Cari judul dataset, wali data OPD, nama teknis layer, atau abstrak..."
                class="w-full min-h-[48px] pl-11 pr-11 py-2.5 bg-white dark:bg-[#0c1522] rounded-xl text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-600/30 transition-shadow"
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

            <!-- OPD Filter + Result Summary -->
            <div class="flex flex-wrap items-center gap-3">
              <div class="relative">
                <select
                  v-model="selectedOpd"
                  aria-label="Filter berdasarkan Wali Data OPD"
                  class="min-h-[48px] pl-4 pr-10 py-2.5 bg-white dark:bg-[#0c1522] rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-600/30 cursor-pointer"
                >
                  <option :value="null">Semua Wali Data OPD ({{ uniqueOpdList.length }})</option>
                  <option v-for="opd in uniqueOpdList" :key="opd" :value="opd">
                    {{ opd }}
                  </option>
                </select>
              </div>

              <button
                v-if="hasActiveFilter"
                type="button"
                class="min-h-[48px] inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-[#EAEFE9] dark:bg-[#132033] text-xs font-semibold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer"
                @click="resetAllFilters"
              >
                <UIcon name="i-lucide-rotate-ccw" class="w-3.5 h-3.5" />
                <span>Atur Ulang Filter</span>
              </button>
            </div>
          </div>

          <!-- Tonal Category Pills -->
          <div
            v-if="categories.length > 0"
            class="mt-4 flex items-center gap-2 overflow-x-auto pb-2 no-scrollbar"
            role="tablist"
            aria-label="Kategori Tematik Dataset"
          >
            <button
              type="button"
              role="tab"
              :aria-selected="!selectedCategory || selectedCategory === 'ALL'"
              class="min-h-[44px] inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer"
              :class="
                !selectedCategory || selectedCategory === 'ALL'
                  ? 'bg-slate-900 text-white dark:bg-emerald-600 dark:text-white'
                  : 'bg-[#EAEFE9]/80 dark:bg-[#0c1522] text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
              "
              @click="selectedCategory = 'ALL'"
            >
              <span>Semua Kategori</span>
              <span
                class="px-2 py-0.5 rounded-full text-[11px] tabular-nums"
                :class="
                  !selectedCategory || selectedCategory === 'ALL'
                    ? 'bg-white/20 text-white'
                    : 'bg-white/80 dark:bg-white/[0.06] text-slate-600 dark:text-slate-400'
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
              class="min-h-[44px] inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer"
              :class="
                selectedCategory === cat.name
                  ? 'bg-slate-900 text-white dark:bg-emerald-600 dark:text-white'
                  : 'bg-[#EAEFE9]/80 dark:bg-[#0c1522] text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
              "
              @click="selectedCategory = selectedCategory === cat.name ? 'ALL' : cat.name"
            >
              <span>{{ cat.name }}</span>
              <span
                class="px-2 py-0.5 rounded-full text-[11px] tabular-nums"
                :class="
                  selectedCategory === cat.name
                    ? 'bg-white/20 text-white'
                    : 'bg-white/80 dark:bg-white/[0.06] text-slate-600 dark:text-slate-400'
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
            <div class="h-6 w-56 bg-[#EAEFE9] dark:bg-[#0c1522] rounded-lg animate-pulse"></div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
              <div
                v-for="j in 3"
                :key="j"
                class="h-64 rounded-2xl bg-white dark:bg-[#0c1522] p-6 animate-pulse flex flex-col justify-between"
              >
                <div class="space-y-3">
                  <div class="h-4 w-28 bg-slate-200 dark:bg-slate-800 rounded"></div>
                  <div class="h-6 w-4/5 bg-slate-200 dark:bg-slate-800 rounded"></div>
                  <div class="h-4 w-full bg-slate-100 dark:bg-slate-800/70 rounded"></div>
                </div>
                <div class="h-10 w-full bg-slate-100 dark:bg-slate-800/70 rounded-xl"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- 5. Error State -->
        <div
          v-else-if="error && allLayers.length === 0"
          class="my-8 rounded-2xl bg-white dark:bg-[#0c1522] p-10 text-center max-w-xl mx-auto"
        >
          <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center mx-auto mb-4">
            <UIcon name="i-lucide-wifi-off" class="w-6 h-6" />
          </div>
          <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-2">
            Koneksi ke Simpul JIGN Sedang Terkendala
          </h2>
          <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed mb-6">
            {{ error }}
          </p>
          <button
            type="button"
            class="min-h-[44px] inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold transition-colors cursor-pointer"
            @click="handleSyncNode"
          >
            <UIcon name="i-lucide-refresh-cw" class="w-4 h-4" />
            <span>Muat Ulang Katalog Dataset</span>
          </button>
        </div>

        <!-- 6. Empty Search/Filter Result -->
        <div
          v-else-if="displayedTotalCount === 0"
          class="my-8 rounded-2xl bg-white dark:bg-[#0c1522] p-12 text-center max-w-xl mx-auto"
        >
          <div class="w-12 h-12 rounded-2xl bg-[#EAEFE9] dark:bg-white/[0.05] text-slate-500 dark:text-slate-400 flex items-center justify-center mx-auto mb-4">
            <UIcon name="i-lucide-search-x" class="w-6 h-6" />
          </div>
          <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-2">
            Dataset Tidak Ditemukan
          </h2>
          <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed mb-6">
            Tidak ada dataset geospasial yang cocok dengan kombinasi kata kunci atau filter wali data yang Anda pilih.
          </p>
          <button
            type="button"
            class="min-h-[44px] inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-slate-900 dark:bg-emerald-600 text-white text-xs font-semibold transition-colors cursor-pointer"
            @click="resetAllFilters"
          >
            <UIcon name="i-lucide-rotate-ccw" class="w-4 h-4" />
            <span>Tampilkan Seluruh Dataset ({{ allLayers.length }})</span>
          </button>
        </div>

        <!-- 7. Grouped Dataset Directory (Tonal Surface Cards, Zero Heavy Boxing) -->
        <div v-else class="space-y-12">
          <!-- Result counter bar -->
          <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400">
            <p>
              Menampilkan <strong class="font-semibold text-slate-900 dark:text-white tabular-nums">{{ displayedTotalCount }}</strong> dari
              <span class="tabular-nums">{{ allLayers.length }}</span> dataset geospasial JIGN Bojonegoro
            </p>
            <p class="text-slate-500 dark:text-slate-400">
              Klik <strong class="font-semibold text-slate-800 dark:text-slate-200">Inspeksi Metadata Lengkap</strong> pada kartu untuk melihat skema ISO 19139, Dublin Core, KUGI BIG, &amp; atribut WFS.
            </p>
          </div>

          <section
            v-for="(layers, categoryName) in displayedGrouped"
            :key="categoryName"
            class="space-y-5"
          >
            <!-- Category Group Header -->
            <div class="flex items-baseline justify-between gap-4 pb-3 border-b border-[#E4EAE3] dark:border-white/[0.07]">
              <div class="flex items-center gap-3">
                <span class="w-2 h-2 rounded-full bg-emerald-600 dark:bg-emerald-400"></span>
                <h2 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                  {{ categoryName }}
                </h2>
              </div>
              <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 tabular-nums">
                {{ layers.length }} dataset
              </span>
            </div>

            <!-- Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
              <article
                v-for="layer in layers"
                :key="layer.id"
                class="group rounded-2xl bg-white dark:bg-[#0c1522] p-6 flex flex-col justify-between transition-all duration-200 hover:shadow-md dark:hover:bg-[#101c2e]"
              >
                <div>
                  <!-- Card Top Meta: OPD Producer & Protocol Badge -->
                  <div class="flex items-start justify-between gap-3 mb-3">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400 line-clamp-1">
                      {{ layer.opd_name || layer.attribution || 'Pemerintah Kab. Bojonegoro' }}
                    </span>
                    <span class="shrink-0 px-2.5 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider bg-[#EAEFE9] dark:bg-white/[0.06] text-slate-700 dark:text-slate-300">
                      OGC {{ (layer.protocol || 'WMS').toUpperCase() }}
                    </span>
                  </div>

                  <!-- Dataset Title -->
                  <h3
                    class="text-base sm:text-lg font-bold text-slate-900 dark:text-white leading-snug mb-2 group-hover:text-emerald-700 dark:group-hover:text-emerald-400 transition-colors cursor-pointer"
                    @click="openMetadataInspector(layer, 'summary')"
                  >
                    {{ layer.name }}
                  </h3>

                  <!-- Abstract Preview -->
                  <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 leading-relaxed line-clamp-2 mb-5">
                    {{ getCleanAbstract(layer) }}
                  </p>
                </div>

                <div class="pt-4 border-t border-[#ECEFEB] dark:border-white/[0.06] space-y-4">
                  <!-- Technical Identifier & Standards Tags -->
                  <div class="flex items-center justify-between gap-2 text-[11px] text-slate-500 dark:text-slate-400">
                    <span class="truncate tabular-nums" :title="layer.layer_name">
                      Layer: <strong class="font-medium text-slate-700 dark:text-slate-300">{{ formatShortLayerName(layer.layer_name) }}</strong>
                    </span>
                    <span class="shrink-0 text-[10px] font-semibold text-slate-500 dark:text-slate-400">
                      ISO 19139 · WFS · CSW
                    </span>
                  </div>

                  <!-- Primary Actions (44px Minimum Touch Target) -->
                  <div class="flex items-center gap-2">
                    <button
                      type="button"
                      class="flex-1 min-h-[44px] inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-xl bg-[#EAEFE9] hover:bg-emerald-700 text-slate-800 hover:text-white dark:bg-white/[0.06] dark:hover:bg-emerald-600 dark:text-slate-200 dark:hover:text-white text-xs font-semibold transition-colors cursor-pointer"
                      @click="openMetadataInspector(layer, 'summary')"
                    >
                      <UIcon name="i-lucide-file-search-2" class="w-4 h-4 shrink-0" />
                      <span>Inspeksi Metadata Lengkap</span>
                    </button>

                    <NuxtLink
                      :to="`/maps?search=${encodeURIComponent(layer.name)}`"
                      aria-label="Visualisasikan layer di Peta WebGIS"
                      title="Visualisasikan di Peta WebGIS"
                      class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-xl bg-[#F4F7F3] dark:bg-white/[0.04] hover:bg-emerald-600/15 text-slate-700 dark:text-slate-300 hover:text-emerald-700 dark:hover:text-emerald-400 transition-colors"
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

    <!-- 8. Comprehensive JIGN Metadata Inspector Modal (Full 4-Tab Master Inspector) -->
    <UModal
      v-model:open="isModalOpen"
      :title="activeLayer?.name || 'Inspeksi Metadata Dataset JIGN'"
      description="Metadata geospasial lengkap Simpul JIGN Kabupaten Bojonegoro standar OGC, ISO 19139, Dublin Core, dan KUGI BIG."
      :ui="{
        overlay: 'z-[100] bg-slate-950/65 backdrop-blur-xs',
        content: 'z-[110] w-[calc(100vw-1.5rem)] sm:w-[calc(100vw-3rem)] sm:max-w-5xl max-h-[88vh] bg-[#F7F8F5] dark:bg-[#070b14] text-slate-900 dark:text-slate-100 rounded-2xl shadow-2xl flex flex-col overflow-hidden ring-1 ring-slate-900/10 dark:ring-white/10 p-0 sm:p-0'
      }"
    >
      <template #content>
        <div v-if="activeLayer" class="flex flex-col max-h-[88vh] overflow-hidden font-sans">

          <!-- Modal Header -->
          <div class="px-6 py-5 bg-white dark:bg-[#0c1522] border-b border-[#E4EAE3] dark:border-white/[0.07] shrink-0">
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                  <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                    {{ activeLayer.category || 'Umum' }}
                  </span>
                  <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider bg-[#EAEFE9] dark:bg-white/[0.06] text-slate-700 dark:text-slate-300">
                    OGC {{ (activeLayer.protocol || 'WMS').toUpperCase() }} · WFS · pyCSW 2.6.0
                  </span>
                  <span
                    v-if="kugiFeatureInfo?.code || attributesData?.detected_fcode"
                    class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider bg-amber-500/10 text-amber-700 dark:text-amber-400 tabular-nums"
                  >
                    KUGI BIG: {{ kugiFeatureInfo?.code || attributesData?.detected_fcode }}
                  </span>
                </div>

                <h2 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white tracking-tight leading-snug">
                  {{ activeLayer.name }}
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                  Wali Data: <strong class="font-semibold text-slate-700 dark:text-slate-200">{{ activeLayer.opd_name || dcRecord?.publisher || isoRecord?.contact?.organisation || 'Pemerintah Kabupaten Bojonegoro' }}</strong>
                </p>
              </div>

              <button
                type="button"
                aria-label="Tutup jendela inspeksi metadata"
                class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-xl bg-[#EAEFE9]/70 dark:bg-white/[0.05] text-slate-500 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer shrink-0"
                @click="isModalOpen = false"
              >
                <UIcon name="i-lucide-x" class="w-5 h-5" />
              </button>
            </div>

            <!-- 4-Tab Navigation Bar -->
            <div class="mt-5 flex items-center gap-1.5 overflow-x-auto no-scrollbar pt-1" role="tablist">
              <button
                type="button"
                role="tab"
                :aria-selected="activeTab === 'summary'"
                class="min-h-[44px] inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer"
                :class="
                  activeTab === 'summary'
                    ? 'bg-slate-900 text-white dark:bg-emerald-600 dark:text-white'
                    : 'bg-[#F4F7F3] dark:bg-white/[0.04] text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                "
                @click="activeTab = 'summary'"
              >
                <UIcon name="i-lucide-layers" class="w-4 h-4" />
                <span>Ringkasan &amp; Layanan OGC</span>
              </button>

              <button
                type="button"
                role="tab"
                :aria-selected="activeTab === 'standards'"
                class="min-h-[44px] inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer"
                :class="
                  activeTab === 'standards'
                    ? 'bg-slate-900 text-white dark:bg-emerald-600 dark:text-white'
                    : 'bg-[#F4F7F3] dark:bg-white/[0.04] text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                "
                @click="activeTab = 'standards'"
              >
                <UIcon name="i-lucide-file-badge" class="w-4 h-4" />
                <span>Metadata ISO 19139 &amp; Dublin Core</span>
              </button>

              <button
                type="button"
                role="tab"
                :aria-selected="activeTab === 'spatial'"
                class="min-h-[44px] inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer"
                :class="
                  activeTab === 'spatial'
                    ? 'bg-slate-900 text-white dark:bg-emerald-600 dark:text-white'
                    : 'bg-[#F4F7F3] dark:bg-white/[0.04] text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                "
                @click="activeTab = 'spatial'"
              >
                <UIcon name="i-lucide-compass" class="w-4 h-4" />
                <span>Cakupan Spasial &amp; BBOX</span>
              </button>

              <button
                type="button"
                role="tab"
                :aria-selected="activeTab === 'attributes'"
                class="min-h-[44px] inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer"
                :class="
                  activeTab === 'attributes'
                    ? 'bg-slate-900 text-white dark:bg-emerald-600 dark:text-white'
                    : 'bg-[#F4F7F3] dark:bg-white/[0.04] text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                "
                @click="activeTab = 'attributes'"
              >
                <UIcon name="i-lucide-table-properties" class="w-4 h-4" />
                <span>Skema Atribut WFS &amp; KUGI BIG</span>
                <span
                  v-if="attributesData?.attributes_count"
                  class="px-2 py-0.5 rounded-full text-[10px] tabular-nums"
                  :class="activeTab === 'attributes' ? 'bg-white/20 text-white' : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'"
                >
                  {{ attributesData.attributes_count }}
                </span>
              </button>
            </div>
          </div>

          <!-- Modal Body (Scrollable) -->
          <div class="flex-1 overflow-y-auto p-6 space-y-6">

            <!-- Loading Indicator for Live CSW / WFS Inspection -->
            <div
              v-if="loadingInspector && !fullMetadata && !attributesData"
              class="rounded-2xl bg-white dark:bg-[#0c1522] p-12 text-center space-y-3"
            >
              <UIcon name="i-lucide-loader-2" class="w-7 h-7 text-emerald-600 dark:text-emerald-400 animate-spin mx-auto" />
              <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                Mengambil rekaman metadata lengkap dari Simpul JIGN Bojonegoro...
              </p>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                Menghubungi katalog pyCSW 2.6.0 (ISO 19139 &amp; Dublin Core), WFS DescribeFeatureType, serta kamus KUGI BIG.
              </p>
            </div>

            <!-- TAB 1: RINGKASAN, LEGENDA & SPESIFIKASI LAYANAN OGC -->
            <div v-if="activeTab === 'summary'" class="space-y-6">
              <!-- Abstract & Narrative -->
              <div class="rounded-2xl bg-white dark:bg-[#0c1522] p-6">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400 mb-2">
                  Abstrak &amp; Deskripsi Dataset
                </h3>
                <p class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-line">
                  {{ dcRecord?.abstract || activeLayer.description || 'Dataset geospasial tematik yang dipublikasikan melalui Simpul Jaringan Informasi Geospasial Nasional (JIGN) Pemerintah Kabupaten Bojonegoro.' }}
                </p>

                <!-- Subject Keywords -->
                <div v-if="dcRecord?.subjects && dcRecord.subjects.length > 0" class="mt-4 pt-4 border-t border-[#ECEFEB] dark:border-white/[0.06]">
                  <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">
                    Kata Kunci Tematik (Subjects)
                  </p>
                  <div class="flex flex-wrap gap-1.5">
                    <span
                      v-for="kw in dcRecord.subjects"
                      :key="kw"
                      class="px-2.5 py-1 rounded-lg bg-[#F4F7F3] dark:bg-white/[0.04] text-xs font-medium text-slate-700 dark:text-slate-300"
                    >
                      {{ kw }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Core Identity Grid + Map Legend Symbol -->
              <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <div class="lg:col-span-8 rounded-2xl bg-white dark:bg-[#0c1522] p-6">
                  <h3 class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400 mb-4">
                    Identitas &amp; Kepemilikan Data
                  </h3>
                  <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-xs">
                    <div class="pb-3 border-b border-[#ECEFEB] dark:border-white/[0.06]">
                      <dt class="text-slate-500 dark:text-slate-400 mb-1">Produsen / Wali Data OPD</dt>
                      <dd class="font-semibold text-slate-900 dark:text-white">
                        {{ activeLayer.opd_name || dcRecord?.publisher || isoRecord?.contact?.organisation || 'Pemerintah Kabupaten Bojonegoro' }}
                      </dd>
                    </div>
                    <div class="pb-3 border-b border-[#ECEFEB] dark:border-white/[0.06]">
                      <dt class="text-slate-500 dark:text-slate-400 mb-1">Kategori Urusan</dt>
                      <dd class="font-semibold text-slate-900 dark:text-white">
                        {{ activeLayer.category || isoRecord?.topic_category || 'Geospasial Daerah' }}
                      </dd>
                    </div>
                    <div class="pb-3 border-b border-[#ECEFEB] dark:border-white/[0.06]">
                      <dt class="text-slate-500 dark:text-slate-400 mb-1">Nama Teknis Layer (Workspace:Layer)</dt>
                      <dd class="font-semibold text-slate-900 dark:text-white break-all tabular-nums">
                        {{ activeLayer.layer_name }}
                      </dd>
                    </div>
                    <div class="pb-3 border-b border-[#ECEFEB] dark:border-white/[0.06]">
                      <dt class="text-slate-500 dark:text-slate-400 mb-1">Identifier Katalog JIGN (UUID)</dt>
                      <dd class="font-semibold text-slate-900 dark:text-white break-all tabular-nums">
                        {{ activeLayer.geoportal_id || isoRecord?.file_identifier || dcRecord?.identifier || activeLayer.layer_name }}
                      </dd>
                    </div>
                    <div class="pb-3 sm:pb-0 border-b sm:border-b-0 border-[#ECEFEB] dark:border-white/[0.06]">
                      <dt class="text-slate-500 dark:text-slate-400 mb-1">Sistem Referensi Koordinat (CRS)</dt>
                      <dd class="font-semibold text-emerald-700 dark:text-emerald-400 tabular-nums">
                        {{ isoRecord?.crs || dcRecord?.crs || 'EPSG:4326 (WGS 84 / SRGI 2013)' }}
                      </dd>
                    </div>
                    <div>
                      <dt class="text-slate-500 dark:text-slate-400 mb-1">Sinkronisasi Terakhir</dt>
                      <dd class="font-semibold text-slate-900 dark:text-white tabular-nums">
                        {{ formatDateFull(activeLayer.last_synced_at || isoRecord?.date_stamp || dcRecord?.date) }}
                      </dd>
                    </div>
                  </dl>
                </div>

                <!-- Legend Graphic Preview -->
                <div class="lg:col-span-4 rounded-2xl bg-white dark:bg-[#0c1522] p-6 flex flex-col justify-between">
                  <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400 mb-2">
                      Simbologi Legenda WMS
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                      Representasi kartografi resmi dari GeoServer JIGN (`GetLegendGraphic`).
                    </p>
                  </div>

                  <div class="flex-1 flex items-center justify-center rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03] p-4 min-h-[120px]">
                    <img
                      v-if="activeLayer.legend_url && !legendLoadError"
                      :src="activeLayer.legend_url"
                      :alt="`Legenda ${activeLayer.name}`"
                      class="max-h-36 object-contain rounded bg-white p-2 shadow-xs"
                      @error="legendLoadError = true"
                    />
                    <div v-else class="text-center text-xs text-slate-400 dark:text-slate-500">
                      <UIcon name="i-lucide-image-off" class="w-5 h-5 mx-auto mb-1 opacity-60" />
                      <span>Pratinjau simbol tersedia saat layer dimuat di peta</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- OGC Interoperability Endpoints (Copyable) -->
              <div class="rounded-2xl bg-white dark:bg-[#0c1522] p-6 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                      Titik Akses Layanan Interoperabilitas OGC (Endpoints)
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                      Gunakan URL standar OGC berikut untuk menghubungkan dataset ke QGIS, ArcGIS, atau aplikasi WebGIS eksternal.
                    </p>
                  </div>
                </div>

                <div class="space-y-3">
                  <div
                    v-for="ep in ogcEndpointsList"
                    :key="ep.label"
                    class="rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03] p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                  >
                    <div class="min-w-0 flex-1">
                      <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-600/10 text-emerald-700 dark:text-emerald-400">
                          {{ ep.badge }}
                        </span>
                        <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">
                          {{ ep.label }}
                        </span>
                      </div>
                      <p class="text-xs text-slate-500 dark:text-slate-400 break-all tabular-nums">
                        {{ ep.url }}
                      </p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                      <button
                        type="button"
                        class="min-h-[44px] inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white dark:bg-[#0c1522] hover:bg-emerald-600 hover:text-white text-xs font-semibold text-slate-700 dark:text-slate-200 transition-colors cursor-pointer"
                        @click="copyText(ep.url, ep.label)"
                      >
                        <UIcon :name="copiedKey === ep.label ? 'i-lucide-check' : 'i-lucide-copy'" class="w-3.5 h-3.5" />
                        <span>{{ copiedKey === ep.label ? 'Tersalin' : 'Salin URL' }}</span>
                      </button>

                      <a
                        :href="ep.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        :aria-label="`Buka ${ep.label} di tab baru`"
                        class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-xl bg-white dark:bg-[#0c1522] text-slate-600 dark:text-slate-300 hover:text-emerald-700 dark:hover:text-emerald-400 transition-colors"
                      >
                        <UIcon name="i-lucide-external-link" class="w-4 h-4" />
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- TAB 2: METADATA STANDAR ISO 19139 & DUBLIN CORE (pyCSW 2.6.0) -->
            <div v-else-if="activeTab === 'standards'" class="space-y-6">
              <!-- Official CSW XML Actions Banner -->
              <div class="rounded-2xl bg-[#EAEFE9]/80 dark:bg-[#0c1522] p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                  <div class="flex items-center gap-2 mb-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-600 dark:bg-emerald-400"></span>
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-400">
                      Katalog Standar OGC CSW 2.0.2 (pyCSW 2.6.0)
                    </span>
                  </div>
                  <p class="text-xs text-slate-600 dark:text-slate-400">
                    Dokumen metadata XML resmi sesuai Peraturan BIG tentang Standar Metadata Spasial Nasional (SNI ISO 19115/19139).
                  </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                  <a
                    :href="getLocalXmlMirrorUrl('iso', false)"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="min-h-[44px] inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white dark:bg-[#132033] text-xs font-semibold text-slate-800 dark:text-slate-200 hover:bg-emerald-700 hover:text-white transition-colors"
                  >
                    <UIcon name="i-lucide-file-code" class="w-4 h-4" />
                    <span>Lihat XML ISO 19139</span>
                  </a>
                  <a
                    :href="getLocalXmlMirrorUrl('iso', true)"
                    class="min-h-[44px] inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-xs font-semibold text-white transition-colors"
                  >
                    <UIcon name="i-lucide-download" class="w-4 h-4" />
                    <span>Unduh ISO 19139 (.xml)</span>
                  </a>
                  <a
                    :href="getLocalXmlMirrorUrl('dc', true)"
                    class="min-h-[44px] inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white dark:bg-[#132033] text-xs font-semibold text-slate-800 dark:text-slate-200 hover:bg-slate-900 hover:text-white transition-colors"
                  >
                    <UIcon name="i-lucide-download" class="w-4 h-4" />
                    <span>Unduh Dublin Core (.xml)</span>
                  </a>
                </div>
              </div>

              <!-- ISO 19115 / 19139 Complete Metadata Section -->
              <div class="rounded-2xl bg-white dark:bg-[#0c1522] p-6">
                <div class="flex items-center justify-between gap-2 pb-4 mb-5 border-b border-[#ECEFEB] dark:border-white/[0.06]">
                  <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                      1. Skema Metadata Geospasial ISO 19115 / ISO 19139 (gmd:MD_Metadata)
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                      Profil metadata geospasial nasional untuk pertukaran data antar Simpul Jaringan JIGN.
                    </p>
                  </div>
                  <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 text-[11px] font-semibold">
                    SNI ISO 19115
                  </span>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 text-xs">
                  <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <dt class="text-slate-500 dark:text-slate-400 mb-1">File Identifier (gmd:fileIdentifier)</dt>
                    <dd class="font-semibold text-slate-900 dark:text-white break-all tabular-nums">
                      {{ isoRecord?.file_identifier || activeLayer.geoportal_id || activeLayer.layer_name }}
                    </dd>
                  </div>

                  <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <dt class="text-slate-500 dark:text-slate-400 mb-1">Tanggal Publikasi Metadata (gmd:dateStamp)</dt>
                    <dd class="font-semibold text-slate-900 dark:text-white tabular-nums">
                      {{ isoRecord?.date_stamp || dcRecord?.date || formatDateFull(activeLayer.last_synced_at) }}
                    </dd>
                  </div>

                  <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <dt class="text-slate-500 dark:text-slate-400 mb-1">Kategori Topik ISO (gmd:topicCategory)</dt>
                    <dd class="font-semibold text-slate-900 dark:text-white capitalize">
                      {{ isoRecord?.topic_category || activeLayer.category || 'geoscientificInformation / planningCadastre' }}
                    </dd>
                  </div>

                  <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <dt class="text-slate-500 dark:text-slate-400 mb-1">Sistem Referensi Spasial (gmd:referenceSystemInfo)</dt>
                    <dd class="font-semibold text-emerald-700 dark:text-emerald-400 tabular-nums">
                      {{ isoRecord?.crs || dcRecord?.crs || 'EPSG:4326 (WGS 84)' }}
                    </dd>
                  </div>

                  <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <dt class="text-slate-500 dark:text-slate-400 mb-1">Skala Resolusi Spasial (gmd:equivalentScale)</dt>
                    <dd class="font-semibold text-slate-900 dark:text-white tabular-nums">
                      {{ isoRecord?.scale_denominator ? `1 : ${Number(isoRecord.scale_denominator).toLocaleString('id-ID')}` : '1 : 25.000 (Skala Daerah)' }}
                    </dd>
                  </div>

                  <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <dt class="text-slate-500 dark:text-slate-400 mb-1">Bahasa Metadata (gmd:language)</dt>
                    <dd class="font-semibold text-slate-900 dark:text-white uppercase">
                      {{ isoRecord?.language || 'ind / eng (Bahasa Indonesia)' }}
                    </dd>
                  </div>

                  <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <dt class="text-slate-500 dark:text-slate-400 mb-1">Organisasi Penanggung Jawab (gmd:CI_ResponsibleParty)</dt>
                    <dd class="font-semibold text-slate-900 dark:text-white">
                      {{ isoRecord?.contact?.organisation || activeLayer.opd_name || 'Bappeda / Dinas PU Bina Marga Kab. Bojonegoro' }}
                    </dd>
                  </div>

                  <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <dt class="text-slate-500 dark:text-slate-400 mb-1">Pejabat / Walidata Teknis (individualName)</dt>
                    <dd class="font-semibold text-slate-900 dark:text-white">
                      {{ isoRecord?.contact?.individual || dcRecord?.creator || 'Tim Simpul Jaringan Geospasial Bojonegoro' }}
                    </dd>
                  </div>

                  <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <dt class="text-slate-500 dark:text-slate-400 mb-1">Peran Kontak (gmd:CI_RoleCode)</dt>
                    <dd class="font-semibold text-slate-900 dark:text-white capitalize">
                      {{ isoRecord?.contact?.role || 'pointOfContact / custodian' }}
                    </dd>
                  </div>
                </dl>

                <!-- Lineage & Supplemental Information -->
                <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="p-4 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                      Silsilah &amp; Kualitas Data (gmd:LI_Lineage)
                    </p>
                    <p class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
                      {{ isoRecord?.lineage || 'Data diproduksi dan diverifikasi oleh Wali Data Perangkat Daerah Kabupaten Bojonegoro serta disinkronisasikan melalui infrastruktur GeoServer & pyCSW JIGN.' }}
                    </p>
                  </div>
                  <div class="p-4 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                      Informasi Tambahan (gmd:supplementalInformation)
                    </p>
                    <p class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
                      {{ isoRecord?.supplemental_info || 'Dapat diakses publik sesuai kebijakan Satu Data Indonesia dan Keterbukaan Informasi Geospasial Kabupaten Bojonegoro.' }}
                    </p>
                  </div>
                </div>
              </div>

              <!-- Dublin Core (csw:Record) Complete Section -->
              <div class="rounded-2xl bg-white dark:bg-[#0c1522] p-6">
                <div class="flex items-center justify-between gap-2 pb-4 mb-5 border-b border-[#ECEFEB] dark:border-white/[0.06]">
                  <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                      2. Skema Dublin Core Metadata Initiative (csw:Record)
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                      Elemen metadata inti dari katalog OGC CSW 2.0.2 untuk penemuan lintas portal.
                    </p>
                  </div>
                  <span class="px-2.5 py-1 rounded-lg bg-[#EAEFE9] dark:bg-white/[0.06] text-slate-700 dark:text-slate-300 text-[11px] font-semibold">
                    CSW 2.0.2
                  </span>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
                  <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <dt class="text-slate-500 dark:text-slate-400 mb-1">dc:identifier</dt>
                    <dd class="font-semibold text-slate-900 dark:text-white break-all tabular-nums">
                      {{ dcRecord?.identifier || activeLayer.geoportal_id || activeLayer.layer_name }}
                    </dd>
                  </div>
                  <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <dt class="text-slate-500 dark:text-slate-400 mb-1">dc:title</dt>
                    <dd class="font-semibold text-slate-900 dark:text-white">
                      {{ dcRecord?.title || activeLayer.name }}
                    </dd>
                  </div>
                  <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <dt class="text-slate-500 dark:text-slate-400 mb-1">dc:type</dt>
                    <dd class="font-semibold text-slate-900 dark:text-white">
                      {{ dcRecord?.type || 'dataset / geospatial-vector' }}
                    </dd>
                  </div>
                  <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <dt class="text-slate-500 dark:text-slate-400 mb-1">dc:publisher</dt>
                    <dd class="font-semibold text-slate-900 dark:text-white">
                      {{ dcRecord?.publisher || activeLayer.opd_name || 'Pemerintah Kabupaten Bojonegoro' }}
                    </dd>
                  </div>
                  <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <dt class="text-slate-500 dark:text-slate-400 mb-1">dc:creator</dt>
                    <dd class="font-semibold text-slate-900 dark:text-white">
                      {{ dcRecord?.creator || activeLayer.opd_name || 'Simpul JIGN Kabupaten Bojonegoro' }}
                    </dd>
                  </div>
                  <div class="p-3.5 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <dt class="text-slate-500 dark:text-slate-400 mb-1">dc:rights (Lisensi &amp; Akses)</dt>
                    <dd class="font-semibold text-slate-900 dark:text-white">
                      {{ dcRecord?.rights || 'Terbuka untuk Publik (Satu Data Indonesia)' }}
                    </dd>
                  </div>
                </dl>

                <!-- References List from Dublin Core -->
                <div v-if="dcRecord?.references && dcRecord.references.length > 0" class="mt-5 pt-4 border-t border-[#ECEFEB] dark:border-white/[0.06]">
                  <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">
                    Referensi Skema Terkait (dct:references)
                  </p>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <a
                      v-for="(refItem, idx) in dcRecord.references"
                      :key="idx"
                      :href="refItem.url"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="p-3 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03] hover:bg-[#EAEFE9] dark:hover:bg-white/[0.07] flex items-center justify-between gap-3 transition-colors"
                    >
                      <div class="min-w-0">
                        <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-400 truncate">
                          {{ refItem.scheme || 'OGC Reference' }}
                        </p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate tabular-nums">
                          {{ refItem.url }}
                        </p>
                      </div>
                      <UIcon name="i-lucide-external-link" class="w-4 h-4 text-slate-400 shrink-0" />
                    </a>
                  </div>
                </div>
              </div>
            </div>

            <!-- TAB 3: CAKUPAN SPASIAL & BOUNDING BOX (WGS 84) -->
            <div v-else-if="activeTab === 'spatial'" class="space-y-6">
              <div class="rounded-2xl bg-white dark:bg-[#0c1522] p-6">
                <div class="flex flex-wrap items-center justify-between gap-3 pb-4 mb-6 border-b border-[#ECEFEB] dark:border-white/[0.06]">
                  <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                      Batas Geografis Dataset (EX_GeographicBoundingBox · WGS 84)
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                      Koordinat cakupan ekstrem barat, timur, selatan, dan utara dalam sistem proyeksi EPSG:4326.
                    </p>
                  </div>
                  <div class="flex items-center gap-2">
                    <button
                      type="button"
                      class="min-h-[44px] inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#F4F7F3] dark:bg-white/[0.04] hover:bg-emerald-600 hover:text-white text-xs font-semibold text-slate-700 dark:text-slate-200 transition-colors cursor-pointer"
                      @click="copyText(bboxWktString, 'WKT BBOX')"
                    >
                      <UIcon :name="copiedKey === 'WKT BBOX' ? 'i-lucide-check' : 'i-lucide-copy'" class="w-3.5 h-3.5" />
                      <span>{{ copiedKey === 'WKT BBOX' ? 'WKT Tersalin' : 'Salin WKT Polygon' }}</span>
                    </button>
                    <button
                      type="button"
                      class="min-h-[44px] inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#F4F7F3] dark:bg-white/[0.04] hover:bg-emerald-600 hover:text-white text-xs font-semibold text-slate-700 dark:text-slate-200 transition-colors cursor-pointer"
                      @click="copyText(bboxGeoJsonString, 'GeoJSON BBOX')"
                    >
                      <UIcon :name="copiedKey === 'GeoJSON BBOX' ? 'i-lucide-check' : 'i-lucide-copy'" class="w-3.5 h-3.5" />
                      <span>{{ copiedKey === 'GeoJSON BBOX' ? 'GeoJSON Tersalin' : 'Salin GeoJSON' }}</span>
                    </button>
                  </div>
                </div>

                <!-- 4 Cardinal Compass Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                  <div class="p-4 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 block mb-1">
                      Batas Barat (West Bound)
                    </span>
                    <p class="text-lg font-bold text-slate-900 dark:text-white tabular-nums">
                      {{ activeBbox.west.toFixed(6) }}° BT
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 tabular-nums mt-0.5">
                      {{ formatDms(activeBbox.west, 'lon') }}
                    </p>
                  </div>

                  <div class="p-4 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 block mb-1">
                      Batas Timur (East Bound)
                    </span>
                    <p class="text-lg font-bold text-slate-900 dark:text-white tabular-nums">
                      {{ activeBbox.east.toFixed(6) }}° BT
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 tabular-nums mt-0.5">
                      {{ formatDms(activeBbox.east, 'lon') }}
                    </p>
                  </div>

                  <div class="p-4 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 block mb-1">
                      Batas Selatan (South Bound)
                    </span>
                    <p class="text-lg font-bold text-slate-900 dark:text-white tabular-nums">
                      {{ activeBbox.south.toFixed(6) }}° LS
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 tabular-nums mt-0.5">
                      {{ formatDms(activeBbox.south, 'lat') }}
                    </p>
                  </div>

                  <div class="p-4 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 block mb-1">
                      Batas Utara (North Bound)
                    </span>
                    <p class="text-lg font-bold text-slate-900 dark:text-white tabular-nums">
                      {{ activeBbox.north.toFixed(6) }}° LS
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 tabular-nums mt-0.5">
                      {{ formatDms(activeBbox.north, 'lat') }}
                    </p>
                  </div>
                </div>

                <!-- Centroid & Span Summary -->
                <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-4 pt-5 border-t border-[#ECEFEB] dark:border-white/[0.06]">
                  <div>
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 block mb-1">
                      Titik Pusat Geografis (Centroid)
                    </span>
                    <p class="text-sm font-bold text-emerald-700 dark:text-emerald-400 tabular-nums">
                      {{ activeBbox.centerLat.toFixed(5) }}°, {{ activeBbox.centerLon.toFixed(5) }}°
                    </p>
                  </div>
                  <div>
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 block mb-1">
                      Estimasi Bentang Wilayah (Barat ke Timur)
                    </span>
                    <p class="text-sm font-bold text-slate-900 dark:text-white tabular-nums">
                      ± {{ activeBbox.widthKm.toFixed(2) }} km
                    </p>
                  </div>
                  <div>
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 block mb-1">
                      Estimasi Bentang Wilayah (Utara ke Selatan)
                    </span>
                    <p class="text-sm font-bold text-slate-900 dark:text-white tabular-nums">
                      ± {{ activeBbox.heightKm.toFixed(2) }} km
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <!-- TAB 4: SKEMA ATRIBUT WFS, KAMUS KUGI BIG & SAMPEL DATA RIIL -->
            <div v-else-if="activeTab === 'attributes'" class="space-y-6">
              <!-- KUGI BIG Feature Identification Banner -->
              <div class="rounded-2xl bg-[#EAEFE9]/80 dark:bg-[#0c1522] p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                  <div>
                    <div class="flex items-center gap-2 mb-1">
                      <span class="w-2 h-2 rounded-full bg-emerald-600 dark:bg-emerald-400"></span>
                      <span class="text-xs font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-400">
                        Katalog Unsur Geospasial Indonesia (KUGI v5 · Badan Informasi Geospasial)
                      </span>
                    </div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">
                      {{ kugiFeatureInfo?.typeName || activeLayer.name }}
                    </h3>
                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-1 max-w-3xl">
                      {{ kugiFeatureInfo?.definition || 'Skema atribut diekstrak secara langsung melalui layanan OGC Web Feature Service (WFS DescribeFeatureType & GetFeature) Simpul JIGN Kabupaten Bojonegoro.' }}
                    </p>
                  </div>

                  <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span
                      v-if="kugiFeatureInfo?.code || attributesData?.detected_fcode"
                      class="px-3 py-1.5 rounded-xl bg-white dark:bg-[#132033] font-bold text-emerald-700 dark:text-emerald-400 tabular-nums"
                    >
                      FCODE: {{ kugiFeatureInfo?.code || attributesData?.detected_fcode }}
                    </span>
                    <span
                      v-if="attributesData?.geom_type"
                      class="px-3 py-1.5 rounded-xl bg-white dark:bg-[#132033] font-semibold text-slate-700 dark:text-slate-200"
                    >
                      Geometri: {{ attributesData.geom_type }}
                    </span>
                    <span
                      v-if="attributesData?.total_features !== null && attributesData?.total_features !== undefined"
                      class="px-3 py-1.5 rounded-xl bg-white dark:bg-[#132033] font-semibold text-slate-700 dark:text-slate-200 tabular-nums"
                    >
                      Total Objek: {{ Number(attributesData.total_features).toLocaleString('id-ID') }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Sub-mode Switcher: Schema Dictionary vs Live WFS Sample Records -->
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="inline-flex items-center gap-1.5 p-1 rounded-xl bg-[#EAEFE9] dark:bg-[#0c1522]">
                  <button
                    type="button"
                    class="min-h-[40px] px-4 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer"
                    :class="
                      attributeViewMode === 'schema'
                        ? 'bg-white dark:bg-emerald-600 text-slate-900 dark:text-white shadow-xs'
                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                    "
                    @click="attributeViewMode = 'schema'"
                  >
                    Struktur Skema Atribut ({{ nonGeometryAttributes.length }})
                  </button>
                  <button
                    type="button"
                    class="min-h-[40px] px-4 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer"
                    :class="
                      attributeViewMode === 'sample'
                        ? 'bg-white dark:bg-emerald-600 text-slate-900 dark:text-white shadow-xs'
                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                    "
                    @click="attributeViewMode = 'sample'"
                  >
                    Sampel Data Riil WFS ({{ sampleRows.length }})
                  </button>
                </div>

                <!-- Attribute Search Filter -->
                <div v-if="attributeViewMode === 'schema'" class="relative w-full sm:w-72">
                  <UIcon
                    name="i-lucide-search"
                    class="w-3.5 h-3.5 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"
                  />
                  <input
                    v-model="attributeSearch"
                    type="search"
                    placeholder="Cari nama kolom atribut..."
                    class="w-full min-h-[40px] pl-9 pr-4 py-1.5 rounded-xl bg-white dark:bg-[#0c1522] text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-600/30"
                  />
                </div>
              </div>

              <!-- Schema Table -->
              <div
                v-if="attributeViewMode === 'schema'"
                class="rounded-2xl bg-white dark:bg-[#0c1522] overflow-hidden"
              >
                <div v-if="filteredSchemaAttributes.length > 0" class="overflow-x-auto">
                  <table class="w-full text-left border-collapse text-xs">
                    <thead>
                      <tr class="bg-[#F4F7F3] dark:bg-white/[0.04] text-slate-600 dark:text-slate-300">
                        <th class="py-3.5 px-4 font-semibold">Nama Atribut</th>
                        <th class="py-3.5 px-4 font-semibold">Definisi Standar KUGI / Deskripsi Kolom</th>
                        <th class="py-3.5 px-4 font-semibold">Tipe Data WFS</th>
                        <th class="py-3.5 px-4 font-semibold">Contoh Nilai Data</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-[#ECEFEB] dark:divide-white/[0.06]">
                      <tr
                        v-for="attr in filteredSchemaAttributes"
                        :key="attr.name"
                        class="hover:bg-[#F7F8F5] dark:hover:bg-white/[0.02]"
                      >
                        <td class="py-3 px-4 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                          {{ attr.name }}
                        </td>
                        <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                          {{ getKugiAttributeDefinition(attr.name) }}
                        </td>
                        <td class="py-3 px-4 whitespace-nowrap">
                          <span class="px-2 py-0.5 rounded bg-[#EAEFE9] dark:bg-white/[0.06] text-slate-700 dark:text-slate-300 font-semibold">
                            {{ attr.local_type || attr.type }}
                          </span>
                        </td>
                        <td class="py-3 px-4 text-slate-600 dark:text-slate-400 tabular-nums max-w-xs truncate">
                          {{ getSampleValueForColumn(attr.name) }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-else class="p-8 text-center text-xs text-slate-500 dark:text-slate-400">
                  Skema atribut WFS sedang dimuat atau tidak ada kolom yang cocok dengan kata kunci pencarian.
                </div>
              </div>

              <!-- Live WFS Sample Data Table -->
              <div
                v-else
                class="rounded-2xl bg-white dark:bg-[#0c1522] overflow-hidden"
              >
                <div v-if="sampleRows.length > 0" class="overflow-x-auto">
                  <table class="w-full text-left border-collapse text-xs">
                    <thead>
                      <tr class="bg-[#F4F7F3] dark:bg-white/[0.04] text-slate-600 dark:text-slate-300">
                        <th class="py-3 px-4 font-semibold whitespace-nowrap">#</th>
                        <th
                          v-for="col in sampleColumns"
                          :key="col"
                          class="py-3 px-4 font-semibold whitespace-nowrap"
                        >
                          {{ col }}
                        </th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-[#ECEFEB] dark:divide-white/[0.06]">
                      <tr
                        v-for="(row, rIdx) in sampleRows"
                        :key="rIdx"
                        class="hover:bg-[#F7F8F5] dark:hover:bg-white/[0.02]"
                      >
                        <td class="py-3 px-4 font-semibold text-slate-400 tabular-nums">
                          {{ rIdx + 1 }}
                        </td>
                        <td
                          v-for="col in sampleColumns"
                          :key="col"
                          class="py-3 px-4 text-slate-700 dark:text-slate-300 whitespace-nowrap max-w-[240px] truncate tabular-nums"
                        >
                          {{ formatCellVal(row[col]) }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-else class="p-8 text-center text-xs text-slate-500 dark:text-slate-400">
                  Sampel fitur WFS belum tersedia untuk layer ini.
                </div>
              </div>
            </div>

          </div>

          <!-- Modal Footer Actions -->
          <div class="px-6 py-4 bg-white dark:bg-[#0c1522] border-t border-[#E4EAE3] dark:border-white/[0.07] flex flex-wrap items-center justify-between gap-3 shrink-0">
            <div class="flex items-center gap-2">
              <button
                type="button"
                :disabled="loadingInspector"
                class="min-h-[44px] inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#EAEFE9] dark:bg-white/[0.06] hover:bg-[#dfe6de] text-xs font-semibold text-slate-700 dark:text-slate-200 transition-colors cursor-pointer disabled:opacity-50"
                @click="loadFullLayerInspection(activeLayer, true)"
              >
                <UIcon
                  name="i-lucide-refresh-cw"
                  class="w-4 h-4"
                  :class="{ 'animate-spin': loadingInspector }"
                />
                <span>Segarkan Metadata Server</span>
              </button>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
              <button
                type="button"
                class="min-h-[44px] px-4 py-2 rounded-xl bg-[#F4F7F3] dark:bg-white/[0.04] text-xs font-semibold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer"
                @click="isModalOpen = false"
              >
                Tutup
              </button>

              <NuxtLink
                :to="`/maps?search=${encodeURIComponent(activeLayer.name)}`"
                class="min-h-[44px] inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500 text-white text-xs font-semibold transition-colors"
                @click="isModalOpen = false"
              >
                <UIcon name="i-lucide-map" class="w-4 h-4" />
                <span>Visualisasikan di Peta WebGIS</span>
              </NuxtLink>
            </div>
          </div>

        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { GeoportalLayerItem } from '~/composables/useGeoportalLayers'

useHead({
  title: 'Katalog Dataset & Metadata JIGN | Simpul Geospasial Kabupaten Bojonegoro',
  meta: [
    {
      name: 'description',
      content: 'Katalog resmi dataset dan metadata geospasial Simpul Jaringan Informasi Geospasial Nasional (JIGN) Pemerintah Kabupaten Bojonegoro berstandar OGC WMS, WFS, CSW, ISO 19139, dan KUGI BIG.'
    }
  ]
})

const config = useRuntimeConfig()

const {
  groupedLayers,
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
  fetchLayerAttributes
} = useGeoportalLayers()

const selectedOpd = ref<string | null>(null)

// Unique OPD list & count
const uniqueOpdList = computed(() => {
  const set = new Set<string>()
  for (const item of allLayers.value) {
    const opd = (item.opd_name || item.attribution || '').trim()
    if (opd) set.add(opd)
  }
  return Array.from(set).sort((a, b) => a.localeCompare(b, 'id'))
})

const uniqueOpdCount = computed(() => uniqueOpdList.value.length || 1)

// Apply OPD filter on top of composable's search & category filter
const displayedGrouped = computed(() => {
  const base = filteredGrouped.value
  if (!selectedOpd.value) return base

  const result: Record<string, GeoportalLayerItem[]> = {}
  for (const [cat, items] of Object.entries(base)) {
    const matched = items.filter((l) => (l.opd_name || l.attribution || '').trim() === selectedOpd.value)
    if (matched.length > 0) {
      result[cat] = matched
    }
  }
  return result
})

const displayedTotalCount = computed(() =>
  Object.values(displayedGrouped.value).reduce((sum, arr) => sum + arr.length, 0)
)

const hasActiveFilter = computed(() =>
  Boolean(
    searchQuery.value.trim() ||
    (selectedCategory.value && selectedCategory.value !== 'ALL') ||
    selectedOpd.value
  )
)

const resetAllFilters = () => {
  searchQuery.value = ''
  selectedCategory.value = 'ALL'
  selectedOpd.value = null
}

// Connection Telemetry Formatting
const statusIndicatorClass = computed(() => {
  const st = connectionStatus.value?.status
  if (st === 'online') return 'bg-emerald-500'
  if (st === 'fallback') return 'bg-amber-500'
  return 'bg-slate-400'
})

const statusLabel = computed(() => {
  const st = connectionStatus.value?.status
  if (st === 'online') return 'Simpul JIGN Terhubung Online'
  if (st === 'fallback') return 'Mode Katalog Lokal Terindeks'
  if (checkingStatus.value) return 'Memeriksa Simpul JIGN...'
  return 'Katalog Tersedia'
})

const handleSyncNode = async () => {
  await Promise.all([
    refresh(),
    checkConnection(true)
  ])
}

const formatRelativeSync = (isoDate?: string | null) => {
  if (!isoDate) return 'Hari ini'
  try {
    const d = new Date(isoDate)
    return d.toLocaleDateString('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric'
    })
  } catch {
    return 'Terbaru'
  }
}

const formatDateFull = (isoDate?: string | null) => {
  if (!isoDate) return 'Terverifikasi di Simpul JIGN'
  try {
    const d = new Date(isoDate)
    if (Number.isNaN(d.getTime())) return isoDate
    return d.toLocaleDateString('id-ID', {
      day: '2-digit',
      month: 'long',
      year: 'numeric'
    })
  } catch {
    return isoDate
  }
}

const formatShortLayerName = (rawName?: string) => {
  if (!rawName) return '-'
  const parts = rawName.split(':')
  return parts[parts.length - 1] || rawName
}

const getCleanAbstract = (layer: GeoportalLayerItem) => {
  const desc = (layer.description || '').trim()
  if (desc && desc.toLowerCase() !== 'none' && desc !== '-') {
    return desc
  }
  return `Dataset spasial tematik ${layer.name} yang dikelola oleh ${layer.opd_name || 'Pemerintah Kabupaten Bojonegoro'} dalam jaringan JIGN.`
}

// ============================================================================
// Full Metadata Inspector State (4 Tabs)
// ============================================================================
type InspectorTab = 'summary' | 'standards' | 'spatial' | 'attributes'

const isModalOpen = ref(false)
const activeLayer = ref<GeoportalLayerItem | null>(null)
const activeTab = ref<InspectorTab>('summary')
const loadingInspector = ref(false)
const legendLoadError = ref(false)

const fullMetadata = ref<any>(null)
const attributesData = ref<any>(null)
const kugiFeatureInfo = ref<any>(null)

const attributeViewMode = ref<'schema' | 'sample'>('schema')
const attributeSearch = ref('')
const copiedKey = ref<string | null>(null)

// Built-in Indonesian KUGI BIG Attribute Dictionary
const KUGI_ATTRIBUTE_DICTIONARY: Record<string, string> = {
  NAMOBJ: 'Nama Objek Geospasial (KUGI BIG)',
  FCODE: 'Kode Unsur Geospasial Indonesia (Feature Code BIG)',
  REMARK: 'Catatan atau Keterangan Tambahan Unsur',
  METADATA: 'Tautan atau Identifier Dokumen Metadata',
  SRS_ID: 'Kode Sistem Referensi Spasial (SRGI 2013 / EPSG)',
  LCODE: 'Kode Klasifikasi Layer Unsur KUGI',
  WADMKD: 'Nama Wilayah Administrasi Kelurahan atau Desa',
  WADMKC: 'Nama Wilayah Administrasi Kecamatan',
  WADMKK: 'Nama Wilayah Administrasi Kabupaten atau Kota',
  WADMPR: 'Nama Wilayah Administrasi Provinsi',
  WIADKC: 'Kode Wilayah Administrasi Kecamatan',
  WIADKK: 'Kode Wilayah Administrasi Kabupaten atau Kota',
  WIADPR: 'Kode Wilayah Administrasi Provinsi',
  WIADKD: 'Kode Wilayah Administrasi Kelurahan atau Desa',
  KDCPUM: 'Kode Cakupan Pemerintahan Umum (Kemendagri / BPS)',
  KDEPUM: 'Kode Desa Pemerintahan Umum',
  LUASWH: 'Luas Wilayah Administrasi atau Poligon (Hektare / m²)',
  SHAPE_LENG: 'Panjang Geometri Garis atau Keliling Poligon',
  SHAPE_LEN: 'Panjang Geometri Garis atau Keliling Poligon',
  SHAPE_AREA: 'Luas Geometri Poligon Spasial',
  PANJANG: 'Panjang Ruas Infrastruktur (Meter / Kilometer)',
  LEBAR: 'Lebar Ruas Penampang Infrastruktur (Meter)',
  KONDISI: 'Klasifikasi Kondisi Fisik Infrastruktur',
  STATUS: 'Status Kewenangan atau Penyelenggaraan Objek',
  TAHUN: 'Tahun Survei atau Tahun Pelaksanaan Pembangunan',
  SUMBER: 'Instansi Produsen atau Sumber Akuisisi Data'
}

const openMetadataInspector = async (layer: GeoportalLayerItem, initialTab: InspectorTab = 'summary') => {
  activeLayer.value = layer
  activeTab.value = initialTab
  legendLoadError.value = false
  attributeViewMode.value = 'schema'
  attributeSearch.value = ''
  isModalOpen.value = true

  await loadFullLayerInspection(layer, false)
}

const loadFullLayerInspection = async (layer: GeoportalLayerItem, forceRefresh = false) => {
  loadingInspector.value = true
  if (forceRefresh) {
    fullMetadata.value = null
    attributesData.value = null
    kugiFeatureInfo.value = null
  }

  try {
    const [metaRes, attrRes] = await Promise.all([
      fetchLayerMetadata(layer.id, forceRefresh).catch(() => null),
      fetchLayerAttributes(layer.id, forceRefresh).catch(() => null)
    ])

    fullMetadata.value = metaRes?.data || metaRes || null
    attributesData.value = attrRes?.data || attrRes || null

    // Lookup KUGI BIG feature dictionary if FCODE is detected
    const fcode = attributesData.value?.detected_fcode
    if (fcode) {
      try {
        const upperCode = String(fcode).trim().toUpperCase()
        const kugiRes = await $http<{ ok: boolean; code: string; data: any[] }>(
          'kugi/feature-type',
          { query: { code: upperCode } }
        )
        const items = Array.isArray(kugiRes?.data) ? kugiRes.data : []
        if (items.length > 0) {
          const first = items[0]
          const attrMap: Record<string, string> = {}
          for (const item of items) {
            if (item?.faCode && item?.ptDefinition) {
              attrMap[String(item.faCode).toUpperCase()] = String(item.ptDefinition).trim()
            }
          }
          kugiFeatureInfo.value = {
            code: upperCode,
            typeName: first.typeName || undefined,
            aliases: first.aliases || undefined,
            definition: first.definition || undefined,
            producer: first.fcProducer || undefined,
            attributes: attrMap
          }
        }
      } catch {
        // Fallback silently if KUGI endpoint is not matched
      }
    }
  } finally {
    loadingInspector.value = false
  }
}

const dcRecord = computed(() => fullMetadata.value?.pycsw?.dublin_core || null)
const isoRecord = computed(() => fullMetadata.value?.pycsw?.iso_19139 || null)

// Bounding Box Computation (Defaults to Bojonegoro Regency extent if layer BBOX is not yet parsed)
const activeBbox = computed(() => {
  const raw = isoRecord.value?.bbox || dcRecord.value?.bbox
  const west = Number(raw?.west ?? 111.416)
  const east = Number(raw?.east ?? 112.152)
  const south = Number(raw?.south ?? -7.468)
  const north = Number(raw?.north ?? -6.985)

  const centerLat = (south + north) / 2
  const centerLon = (west + east) / 2
  const widthKm = Math.abs(east - west) * 111.32 * Math.cos((centerLat * Math.PI) / 180)
  const heightKm = Math.abs(north - south) * 110.57

  return {
    west,
    east,
    south,
    north,
    centerLat,
    centerLon,
    widthKm,
    heightKm
  }
})

const formatDms = (decimal: number, axis: 'lat' | 'lon') => {
  const abs = Math.abs(decimal)
  const deg = Math.floor(abs)
  const minFloat = (abs - deg) * 60
  const min = Math.floor(minFloat)
  const sec = ((minFloat - min) * 60).toFixed(1)
  const dir = axis === 'lat' ? (decimal < 0 ? 'LS' : 'LU') : (decimal < 0 ? 'BB' : 'BT')
  return `${deg}° ${min}' ${sec}" ${dir}`
}

const bboxWktString = computed(() => {
  const b = activeBbox.value
  return `POLYGON((${b.west} ${b.south}, ${b.east} ${b.south}, ${b.east} ${b.north}, ${b.west} ${b.north}, ${b.west} ${b.south}))`
})

const bboxGeoJsonString = computed(() => {
  const b = activeBbox.value
  return JSON.stringify({
    type: 'Feature',
    properties: {
      name: activeLayer.value?.name,
      layer_name: activeLayer.value?.layer_name,
      crs: 'EPSG:4326'
    },
    geometry: {
      type: 'Polygon',
      coordinates: [[
        [b.west, b.south],
        [b.east, b.south],
        [b.east, b.north],
        [b.west, b.north],
        [b.west, b.south]
      ]]
    }
  })
})

// OGC Endpoints Builder
const ogcEndpointsList = computed(() => {
  if (!activeLayer.value) return []
  const layer = activeLayer.value
  const wmsBase = layer.url || 'https://geoportal.bojonegorokab.go.id/geoserver/wms'
  const wfsBase = attributesData.value?.wfs_url || wmsBase.replace(/\/wms$/i, '/wfs')
  const id = encodeURIComponent(layer.geoportal_id || isoRecord.value?.file_identifier || layer.layer_name)

  return [
    {
      badge: 'OGC WMS 1.3.0',
      label: 'Web Map Service (GetCapabilities)',
      url: `${wmsBase}?service=WMS&version=1.3.0&request=GetCapabilities`
    },
    {
      badge: 'OGC WFS 2.0.0',
      label: 'Web Feature Service (GeoJSON Feature Stream)',
      url: `${wfsBase}?service=WFS&version=1.0.0&request=GetFeature&typeName=${encodeURIComponent(layer.layer_name)}&maxFeatures=50&outputFormat=application/json`
    },
    {
      badge: 'OGC CSW 2.0.2',
      label: 'Catalog Service for the Web (ISO 19139 XML Record)',
      url: `https://geoportal.bojonegorokab.go.id/csw/?service=CSW&version=2.0.2&request=GetRecordById&id=${id}&outputSchema=http://www.isotc211.org/2005/gmd&elementSetName=full`
    }
  ]
})

const getLocalXmlMirrorUrl = (schema: 'iso' | 'dc', download = false) => {
  if (!activeLayer.value) return '#'
  const base = String(config.public.apiBase || 'http://localhost:9000').replace(/\/+$/, '')
  const prefix = config.public.apiPrefix || '/api/v1'
  return `${base}${prefix}/layers/geoportal/${activeLayer.value.id}/csw-xml?schema=${schema}${download ? '&download=1' : ''}`
}

// WFS Schema & Sample Helpers
const nonGeometryAttributes = computed(() => {
  const list = attributesData.value?.attributes || []
  return list.filter((a: any) => !a.is_geometry)
})

const filteredSchemaAttributes = computed(() => {
  const q = attributeSearch.value.trim().toLowerCase()
  if (!q) return nonGeometryAttributes.value
  return nonGeometryAttributes.value.filter(
    (a: any) =>
      a.name.toLowerCase().includes(q) ||
      getKugiAttributeDefinition(a.name).toLowerCase().includes(q)
  )
})

const sampleRows = computed<Record<string, any>[]>(() => attributesData.value?.sample_data || [])

const sampleColumns = computed(() => {
  const firstRow = sampleRows.value[0]
  if (firstRow) {
    return Object.keys(firstRow)
  }
  return nonGeometryAttributes.value.map((a: any) => a.name)
})

const getKugiAttributeDefinition = (colName: string) => {
  const upper = (colName || '').toUpperCase()
  if (kugiFeatureInfo.value?.attributes?.[upper]) {
    return kugiFeatureInfo.value.attributes[upper]
  }
  if (KUGI_ATTRIBUTE_DICTIONARY[upper]) {
    return KUGI_ATTRIBUTE_DICTIONARY[upper]
  }
  if (upper.startsWith('ID') || upper === 'GID' || upper === 'FID' || upper === 'OBJECTID') {
    return 'Identifikator Unik Rekaman Fitur Spasial (Primary Key)'
  }
  if (upper.includes('NAMA') || upper.includes('NAME')) {
    return 'Atribut Penamaan Objek atau Entitas Spasial'
  }
  if (upper.includes('KEC')) {
    return 'Atribut Wilayah Administrasi Tingkat Kecamatan'
  }
  if (upper.includes('DESA') || upper.includes('KEL')) {
    return 'Atribut Wilayah Administrasi Tingkat Desa atau Kelurahan'
  }
  return 'Atribut tematik spesifik dari skema wali data perangkat daerah'
}

const getSampleValueForColumn = (colName: string) => {
  for (const row of sampleRows.value) {
    const val = row?.[colName]
    if (val !== null && val !== undefined && String(val).trim() !== '') {
      return String(val)
    }
  }
  return '-'
}

const formatCellVal = (val: any) => {
  if (val === null || val === undefined || val === '') return '-'
  if (typeof val === 'number') return val.toLocaleString('id-ID')
  return String(val)
}

const copyText = async (text: string, key: string) => {
  try {
    await navigator.clipboard.writeText(text)
    copiedKey.value = key
    setTimeout(() => {
      if (copiedKey.value === key) copiedKey.value = null
    }, 2000)
  } catch {
    // Fallback ignored
  }
}
</script>

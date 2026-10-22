<script setup lang="ts">
import type { GeoparkSite } from "~/composables/useGeoparkSites";

const props = defineProps<{
  site: GeoparkSite;
}>();

const emit = defineEmits<{
  (e: "close"): void;
  (e: "back"): void;
  (e: "zoom", site: GeoparkSite): void;
}>();

const copied = ref(false);
const activeImageIndex = ref(0);
const previewModalOpen = ref(false);
const previewImageSrc = ref("");

// Aggregate all available photos: primary image first, followed by supporting gallery
const allPhotos = computed(() => {
  const photos: string[] = [];
  if (props.site.image) {
    photos.push(props.site.image);
  }
  if (Array.isArray(props.site.additionalImages)) {
    for (const url of props.site.additionalImages) {
      if (url && !photos.includes(url)) {
        photos.push(url);
      }
    }
  }
  return photos;
});

const currentMainPhoto = computed(() => {
  if (allPhotos.value.length === 0) return "";
  return allPhotos.value[activeImageIndex.value] || allPhotos.value[0];
});

function openPreview(src: string) {
  previewImageSrc.value = src;
  previewModalOpen.value = true;
}

function closePreview() {
  previewModalOpen.value = false;
  previewImageSrc.value = "";
}

async function copyCoordinates() {
  if (!props.site.hasCoordinate) return;
  const text = `${props.site.latitude.toFixed(6)}, ${props.site.longitude.toFixed(6)}`;
  try {
    await navigator.clipboard.writeText(text);
    copied.value = true;
    setTimeout(() => {
      copied.value = false;
    }, 2000);
  } catch (err) {
    console.error("Gagal menyalin koordinat:", err);
  }
}

function handleZoomClick() {
  emit("zoom", props.site);
}

function getGeoparkCode(site?: GeoparkSite): string {
  if (!site) return "GP";
  if (site.code) return String(site.code);
  const name = String(site.name || site.title || "");
  const words = name.split(" ").filter(Boolean);
  if (words.length >= 2) {
    return (words[0][0] + words[1][0]).toUpperCase();
  }
  return name.slice(0, 3).toUpperCase() || "GP";
}
</script>

<template>
  <div v-if="props.site" class="flex-1 flex flex-col min-h-0 select-none">
    <!-- Header Hero Section: Avatar Bulat, Judul Bold, Subtitle, Tombol Circle X (Selaras SearchItemAboutView) -->
    <div class="px-4 sm:px-5 py-3.5 border-b border-slate-100 dark:border-white/5 flex items-start justify-between gap-3 shrink-0 bg-white dark:bg-[#09111e]">
      <div class="flex items-start gap-3 min-w-0 flex-1">
        <!-- Circular Badge Avatar -->
        <div
          class="size-11 rounded-full text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs tracking-wider"
          :class="props.site.categorySlug === 'geo-diversity'
            ? 'bg-blue-600 dark:bg-blue-500'
            : props.site.categorySlug === 'bio-diversity'
              ? 'bg-emerald-600 dark:bg-emerald-500'
              : 'bg-amber-600 dark:bg-amber-500'"
        >
          {{ getGeoparkCode(props.site) }}
        </div>

        <!-- Title & Subtitle -->
        <div class="min-w-0 flex-1 pt-0.5">
          <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 leading-tight">
            {{ props.site.title || props.site.name }}
          </h1>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-normal truncate">
            {{ props.site.category }} · {{ props.site.tag || 'Bojonegoro' }}
          </p>
        </div>
      </div>

      <!-- Circle X Close Button -->
      <button
        type="button"
        class="size-9 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/20 text-slate-600 dark:text-slate-200 flex items-center justify-center transition-all cursor-pointer shadow-2xs active:scale-95 shrink-0 mt-0.5"
        aria-label="Tutup detail geosite dan kembali ke daftar"
        title="Tutup detail geosite dan kembali ke daftar"
        @click="emit('close')"
      >
        <UIcon name="i-lucide-x" class="size-4" />
      </button>
    </div>

    <!-- Konten Body Scrollable -->
    <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4">
      <!-- HERO BANNER: Foto Utama dengan Overlay Badge -->
      <div class="space-y-2">
        <div class="relative w-full aspect-16/10 rounded-2xl overflow-hidden bg-slate-100 dark:bg-white/5 border border-slate-200/80 dark:border-white/10 shadow-xs group">
          <img
            v-if="currentMainPhoto"
            :src="currentMainPhoto"
            :alt="site.name"
            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105 cursor-pointer"
            loading="lazy"
            @click="openPreview(currentMainPhoto)"
          />
          <div
            v-else
            class="w-full h-full flex flex-col items-center justify-center p-4 text-center bg-gradient-to-br from-emerald-500/10 via-slate-500/5 to-transparent"
          >
            <UIcon name="i-lucide-image-off" class="size-8 text-slate-400 dark:text-slate-500 mb-1" />
            <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Foto resmi belum tersedia</span>
          </div>

          <!-- Overlay Gradient Bawah -->
          <div class="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-black/70 via-black/30 to-transparent pointer-events-none" />

          <!-- Badge Kategori & Kode di Pojok Atas -->
          <div class="absolute top-3 left-3 right-3 flex items-center justify-between gap-2 pointer-events-none">
            <span
              class="px-2.5 py-1 rounded-lg text-[10px] font-bold shadow-xs backdrop-blur-md pointer-events-auto"
              :class="site.categorySlug === 'geo-diversity'
                ? 'bg-blue-600/90 text-white'
                : site.categorySlug === 'bio-diversity'
                  ? 'bg-emerald-600/90 text-white'
                  : 'bg-amber-600/90 text-white'"
            >
              {{ site.category }}
            </span>

            <span class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-black/60 text-white shadow-xs backdrop-blur-md tabular-nums pointer-events-auto">
              {{ site.code }}
            </span>
          </div>

          <!-- Tombol Perbesar Foto di Pojok Bawah -->
          <button
            v-if="currentMainPhoto"
            type="button"
            class="absolute bottom-3 right-3 size-9 rounded-lg bg-black/60 hover:bg-black/80 text-white flex items-center justify-center backdrop-blur-md transition-all cursor-pointer shadow-xs active:scale-95"
            aria-label="Perbesar foto geosite"
            title="Klik untuk melihat foto ukuran penuh"
            @click="openPreview(currentMainPhoto)"
          >
            <UIcon name="i-lucide-maximize-2" class="size-4" />
          </button>
        </div>

        <!-- Carousel / Thumbnail Galeri Foto Pendukung -->
        <div
          v-if="allPhotos.length > 1"
          class="space-y-1.5"
        >
          <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 px-0.5">
            <span class="font-semibold text-slate-700 dark:text-slate-200">
              Galeri Foto Situs
            </span>
            <span class="tabular-nums font-semibold">
              {{ activeImageIndex + 1 }} dari {{ allPhotos.length }} foto
            </span>
          </div>

          <div class="flex items-center gap-2 overflow-x-auto pb-1 pt-0.5 no-scrollbar">
            <button
              v-for="(photoUrl, idx) in allPhotos"
              :key="idx"
              type="button"
              class="relative size-16 rounded-xl overflow-hidden shrink-0 border-2 transition-all cursor-pointer"
              :class="activeImageIndex === idx
                ? 'border-emerald-500 ring-2 ring-emerald-500/20 scale-102'
                : 'border-slate-200/80 dark:border-white/10 opacity-70 hover:opacity-100'"
              :aria-label="`Pilih foto ${idx + 1}`"
              @click="activeImageIndex = idx"
            >
              <img
                :src="photoUrl"
                :alt="`${site.name} foto ${idx + 1}`"
                class="w-full h-full object-cover"
                loading="lazy"
              />
            </button>
          </div>
        </div>
      </div>

      <!-- ═══ 3. IDENTITAS & RINGKASAN SITUS ═════════════════════════════════ -->
      <div class="p-4 rounded-2xl bg-slate-50/80 dark:bg-white/[0.03] border border-slate-200/80 dark:border-white/10 space-y-2">
        <div class="flex items-center gap-2 flex-wrap">
          <span
            v-if="site.tag"
            class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-emerald-100/80 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300"
          >
            #{{ site.tag }}
          </span>
          <span
            class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-200/80 dark:bg-white/10 text-slate-700 dark:text-slate-300 tabular-nums"
          >
            No. Urut {{ site.ordering }}
          </span>
        </div>

        <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 leading-snug">
          {{ site.title }}
        </h2>

        <p
          v-if="site.desc"
          class="text-xs sm:text-[13px] text-slate-600 dark:text-slate-300 leading-relaxed font-normal"
        >
          {{ site.desc }}
        </p>
      </div>

      <!-- ═══ 4. KOORDINAT & AKSI PUSATKAN PETA ═══════════════════════════════ -->
      <div
        v-if="site.hasCoordinate"
        class="p-4 rounded-2xl bg-emerald-50/70 dark:bg-emerald-950/25 border border-emerald-200/80 dark:border-emerald-900/50 space-y-3"
      >
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-2 text-xs font-bold text-emerald-800 dark:text-emerald-300">
            <UIcon name="i-lucide-map-pin" class="size-4 shrink-0" />
            <span>Koordinat Geografis</span>
          </div>

          <button
            type="button"
            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-white dark:bg-white/10 border border-emerald-200/80 dark:border-white/10 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100/60 transition-colors cursor-pointer min-h-[36px]"
            :aria-label="copied ? 'Koordinat berhasil disalin' : 'Salin koordinat geografis'"
            @click="copyCoordinates"
          >
            <UIcon
              :name="copied ? 'i-lucide-check' : 'i-lucide-copy'"
              class="size-3.5"
            />
            <span>{{ copied ? 'Tersalin' : 'Salin' }}</span>
          </button>
        </div>

        <div class="grid grid-cols-2 gap-2 text-xs select-text">
          <div class="p-2.5 rounded-xl bg-white dark:bg-[#0c1424] border border-emerald-200/60 dark:border-white/10">
            <span class="block text-[10px] uppercase font-semibold text-slate-400 dark:text-slate-500 mb-0.5">
              Lintang (Latitude)
            </span>
            <span class="font-semibold text-slate-800 dark:text-slate-200 tabular-nums">
              {{ site.latitude.toFixed(6) }}
            </span>
          </div>

          <div class="p-2.5 rounded-xl bg-white dark:bg-[#0c1424] border border-emerald-200/60 dark:border-white/10">
            <span class="block text-[10px] uppercase font-semibold text-slate-400 dark:text-slate-500 mb-0.5">
              Bujur (Longitude)
            </span>
            <span class="font-semibold text-slate-800 dark:text-slate-200 tabular-nums">
              {{ site.longitude.toFixed(6) }}
            </span>
          </div>
        </div>

        <!-- Tombol Pusatkan Peta -->
        <button
          type="button"
          class="w-full min-h-[44px] px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 shadow-xs flex items-center justify-center gap-2 transition-all cursor-pointer active:scale-98"
          @click="handleZoomClick"
        >
          <UIcon name="i-lucide-crosshair" class="size-4 shrink-0" />
          <span>Pusatkan Peta ke Geosite Ini</span>
        </button>
      </div>

      <!-- Informasi Non-Geografis (Warisan Budaya Takbenda) -->
      <div
        v-else
        class="p-4 rounded-2xl bg-amber-50/70 dark:bg-amber-950/25 border border-amber-200/80 dark:border-amber-900/50 space-y-2 text-xs text-amber-800 dark:text-amber-200"
      >
        <div class="flex items-center gap-2 font-bold">
          <UIcon name="i-lucide-sparkles" class="size-4 text-amber-600 dark:text-amber-400 shrink-0" />
          <span>Warisan Budaya Takbenda</span>
        </div>
        <p class="text-[11.5px] leading-relaxed text-amber-700 dark:text-amber-300 font-normal">
          Situs ini merupakan entitas seni pertunjukan dan warisan tradisi luhur Kabupaten Bojonegoro yang hidup di tengah masyarakat secara dinamis, sehingga tidak terikat pada satu titik koordinat spasial tunggal.
        </p>
      </div>

      <!-- ═══ 5. URAIAN & NARASI LENGKAP RESMI (DARI ENDPOINT) ═══════════════ -->
      <div
        v-if="site.narasiHtml"
        class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-[#0c1424] border border-slate-200/80 dark:border-white/10 space-y-3 shadow-2xs"
      >
        <div class="flex items-center gap-2 pb-2 border-b border-slate-100 dark:border-white/5">
          <UIcon name="i-lucide-book-open" class="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
          <h2 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">
            Narasi & Pembahasan Ilmiah
          </h2>
        </div>

        <div
          class="geopark-prose select-text text-xs sm:text-[12.5px] leading-relaxed text-slate-600 dark:text-slate-300 space-y-3 font-normal"
          v-html="site.narasiHtml"
        />
      </div>

      <!-- ═══ 6. TAUTAN RESMI PEMERINTAH ═════════════════════════════════════ -->
      <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-white/[0.02] border border-slate-200/80 dark:border-white/10 flex items-center justify-between gap-3 text-xs">
        <div class="min-w-0">
          <span class="block text-[10px] uppercase font-semibold text-slate-400 dark:text-slate-500">
            Portal Informasi Resmi
          </span>
          <span class="font-medium text-slate-700 dark:text-slate-300 truncate block">
            geopark.bojonegorokab.go.id
          </span>
        </div>

        <a
          href="https://geopark.bojonegorokab.go.id"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl font-semibold text-xs text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-white/10 border border-emerald-200/80 dark:border-emerald-900/50 transition-colors cursor-pointer min-h-[44px] shrink-0"
        >
          <span>Kunjungi Web</span>
          <UIcon name="i-lucide-external-link" class="size-3.5" />
        </a>
      </div>

      <!-- ═══ 7. TOMBOL KEMBALI DI BAWAH KONTEN ═════════════════════════════ -->
      <div class="pt-2 pb-6">
        <button
          type="button"
          class="w-full min-h-[44px] px-4 py-2.5 rounded-xl font-semibold text-xs text-slate-600 dark:text-slate-300 bg-slate-100 hover:bg-slate-200 dark:bg-white/5 dark:hover:bg-white/10 border border-slate-200/80 dark:border-white/10 flex items-center justify-center gap-2 transition-all cursor-pointer active:scale-98"
          @click="emit('close')"
        >
          <UIcon name="i-lucide-arrow-left" class="size-4" />
          <span>Kembali ke Daftar Geosite</span>
        </button>
      </div>
    </div>

    <!-- ═══ 8. MODAL PREVIEW FOTO UKURAN BESAR (LIGHTBOX) ════════════════════ -->
    <Teleport to="body">
      <div
        v-if="previewModalOpen"
        class="fixed inset-0 z-50 bg-black/90 backdrop-blur-md flex flex-col items-center justify-center p-4"
        @click.self="closePreview"
      >
        <button
          type="button"
          class="absolute top-4 right-4 size-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-all cursor-pointer"
          aria-label="Tutup preview foto"
          @click="closePreview"
        >
          <UIcon name="i-lucide-x" class="size-6" />
        </button>

        <div class="max-w-4xl max-h-[85vh] overflow-hidden rounded-2xl shadow-2xl">
          <img
            :src="previewImageSrc"
            :alt="site.name"
            class="max-w-full max-h-[85vh] object-contain rounded-2xl"
          />
        </div>

        <p class="mt-3 text-xs text-slate-300 font-medium text-center">
          {{ site.title }}
        </p>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

:deep(.geopark-prose p) {
  margin-bottom: 0.75rem;
}

:deep(.geopark-prose strong) {
  font-weight: 700;
  color: inherit;
}

:deep(.geopark-prose em) {
  font-style: italic;
}

:deep(.geopark-prose a) {
  color: #059669;
  text-decoration: underline;
}

:deep(.geopark-prose h3),
:deep(.geopark-prose h4) {
  font-size: 0.875rem;
  font-weight: 700;
  margin-top: 1rem;
  margin-bottom: 0.5rem;
  color: inherit;
}
</style>

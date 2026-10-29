<script setup lang="ts">
import DOMPurify from "dompurify";
import { useGeoparkSites, type GeoparkSite } from "~/composables/useGeoparkSites";

const route = useRoute();
const slug = computed(() => String(route.params.slug || ""));

const { sites, loading, fetchLiveSites } = useGeoparkSites();

onMounted(() => {
  if (sites.value.length === 0) {
    fetchLiveSites();
  }
});

const site = computed<GeoparkSite | null>(() => {
  return sites.value.find((s) => s.slug === slug.value) || null;
});

const pageTitle = computed(() => site.value?.name || "Detail Geosite");
const pageDescription = computed(() => site.value?.desc || "Ulasan resmi situs Geopark Nasional Bojonegoro.");

useSeoMeta({
  title: () => `${pageTitle.value} | Geopark Bojonegoro`,
  description: () => pageDescription.value,
  ogImage: () => site.value?.image || undefined,
});

const sanitizedNarasi = computed(() => {
  if (!site.value?.narasiHtml) return "";
  if (import.meta.server) return site.value.narasiHtml;
  return DOMPurify.sanitize(site.value.narasiHtml);
});

const isCopied = ref(false);
function copyCoordinates() {
  if (!site.value?.hasCoordinate) return;
  const text = `${site.value.coordinate[1]}, ${site.value.coordinate[0]}`;
  navigator.clipboard.writeText(text);
  isCopied.value = true;
  setTimeout(() => {
    isCopied.value = false;
  }, 2000);
}

function handleOpenInMap() {
  if (!site.value) return;
  if (site.value.hasCoordinate) {
    navigateTo(`/maps?geosite=${site.value.slug}`);
  } else {
    navigateTo(`/maps?search=${encodeURIComponent(site.value.name)}`);
  }
}
</script>

<template>
  <PublicPageTemplate
    :title="site ? site.name : 'Memuat Geosite...'"
    :description="pageDescription"
    icon="i-lucide-mountain-snow"
    container-size="narrow"
  >
    <template #actions>
      <div class="flex items-center gap-2">
        <UButton
          to="/geopark"
          color="neutral"
          variant="outline"
          size="sm"
          icon="i-lucide-arrow-left"
          class="rounded-xl font-medium"
        >
          Daftar Geopark
        </UButton>
        <UButton
          v-if="site?.hasCoordinate"
          color="primary"
          variant="solid"
          size="sm"
          icon="i-lucide-map"
          class="rounded-xl font-medium"
          @click="handleOpenInMap"
        >
          Buka di Peta
        </UButton>
      </div>
    </template>

    <div v-if="site" class="py-6 space-y-6">
      <!-- Banner Foto Utama -->
      <div class="relative rounded-2xl overflow-hidden border border-slate-200/80 dark:border-white/10 shadow-md">
        <img
          v-if="site.image"
          :src="site.image"
          :alt="site.name"
          class="w-full h-72 sm:h-96 object-cover"
        />
        <div
          v-else
          class="w-full h-64 bg-slate-100 dark:bg-slate-900 flex items-center justify-center text-slate-400"
        >
          <UIcon name="i-lucide-image" class="size-12" />
        </div>

        <!-- Badges Mengambang di Foto -->
        <div class="absolute bottom-4 left-4 right-4 flex items-center justify-between gap-2">
          <span class="text-xs font-bold px-3 py-1.5 rounded-full bg-slate-900/80 text-white backdrop-blur-md">
            {{ site.category }}
          </span>
          <span class="text-xs font-mono font-semibold px-2.5 py-1 rounded-full bg-white/90 text-slate-900 backdrop-blur-md">
            {{ site.code }}
          </span>
        </div>
      </div>

      <!-- Ringkasan Singkat & Koordinat -->
      <div class="p-5 sm:p-6 rounded-2xl bg-slate-50 dark:bg-white/[0.03] border border-slate-200/80 dark:border-white/10 space-y-4">
        <div>
          <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block mb-1">
            Ringkasan Situs
          </span>
          <p class="text-sm sm:text-base text-slate-700 dark:text-slate-200 leading-relaxed">
            {{ site.desc }}
          </p>
        </div>

        <!-- Koordinat Spasial & Tombol Aksi -->
        <div v-if="site.hasCoordinate" class="pt-4 border-t border-slate-200/60 dark:border-white/5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center gap-2 text-xs sm:text-sm text-slate-600 dark:text-slate-300">
            <UIcon name="i-lucide-crosshair" class="size-4 text-blue-600 dark:text-blue-400 shrink-0" />
            <span class="font-mono">{{ site.coordinate[1].toFixed(6) }}°, {{ site.coordinate[0].toFixed(6) }}°</span>
            <button
              type="button"
              class="text-xs text-blue-600 dark:text-blue-400 hover:underline cursor-pointer ml-1 font-semibold"
              @click="copyCoordinates"
            >
              {{ isCopied ? "Tersalin!" : "Salin" }}
            </button>
          </div>

          <UButton
            color="primary"
            variant="soft"
            size="sm"
            icon="i-lucide-map-pin"
            class="rounded-xl font-medium"
            @click="handleOpenInMap"
          >
            Petakan Lokasi Geosite
          </UButton>
        </div>
      </div>

      <!-- Narasi Ilmiah & Ulasan Lengkap -->
      <section v-if="sanitizedNarasi" class="space-y-3">
        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
          <UIcon name="i-lucide-book-open" class="size-5 text-blue-600 dark:text-blue-400" />
          Ulasan & Narasi Lengkap
        </h2>
        <div
          class="prose prose-slate dark:prose-invert max-w-none text-sm sm:text-base leading-relaxed p-6 rounded-2xl bg-white dark:bg-[#0c1424] border border-slate-200/80 dark:border-white/10"
          v-html="sanitizedNarasi"
        />
      </section>

      <!-- Galeri Foto Pendukung -->
      <section v-if="site.additionalImages && site.additionalImages.length > 0" class="space-y-3">
        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
          <UIcon name="i-lucide-camera" class="size-5 text-blue-600 dark:text-blue-400" />
          Galeri Dokumentasi
        </h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <div
            v-for="(img, idx) in site.additionalImages"
            :key="idx"
            class="rounded-xl overflow-hidden border border-slate-200 dark:border-white/10 h-36 sm:h-44 bg-slate-100 dark:bg-slate-900"
          >
            <img :src="img" :alt="site.name" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300" loading="lazy" />
          </div>
        </div>
      </section>

      <!-- Banner Navigasi Bawah Kembali ke Peta -->
      <div class="p-5 rounded-2xl bg-blue-50/70 dark:bg-blue-950/30 border border-blue-200/70 dark:border-blue-900/40 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h3 class="text-sm font-bold text-blue-950 dark:text-blue-100">
            Ingin mengeksplorasi batas wilayah sekitar {{ site.name }}?
          </h3>
          <p class="text-xs text-blue-800/80 dark:text-blue-300/80 mt-0.5">
            Buka kanvas WebGIS Melarosa untuk melihat jaringan jalan poros desa dan batas kecamatan.
          </p>
        </div>
        <UButton
          color="primary"
          icon="i-lucide-map"
          class="rounded-xl font-medium shrink-0"
          @click="handleOpenInMap"
        >
          Lihat di Peta WebGIS
        </UButton>
      </div>
    </div>

    <!-- State Kosong jika slug tidak cocok -->
    <div
      v-else-if="!loading"
      class="py-16 text-center space-y-4"
    >
      <UIcon name="i-lucide-alert-circle" class="size-10 text-amber-500 mx-auto" />
      <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">
        Situs Geopark Tidak Ditemukan
      </h3>
      <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
        Situs dengan pengenal "{{ slug }}" tidak terdaftar dalam direktori resmi Geopark Bojonegoro.
      </p>
      <UButton
        to="/geopark"
        color="neutral"
        variant="soft"
        icon="i-lucide-arrow-left"
        class="rounded-xl font-medium"
      >
        Kembali ke Katalog Geopark
      </UButton>
    </div>
  </PublicPageTemplate>
</template>

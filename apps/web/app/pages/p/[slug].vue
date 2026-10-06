<script setup lang="ts">
const route = useRoute();
const slug = computed(() => String(route.params.slug || ''));

const { currentHeader, flatHeaderMap } = usePublicNavigation();

// Dynamic registry of predefined public page content
const pageDatabase = computed<Record<string, { title: string; description: string; contentHtml?: string; sections?: Array<{ title: string; body: string }> }>>(() => ({
  informasi: {
    title: 'Informasi Publik Geospasial',
    description: 'Pusat transparansi dan direktori data spasial resmi Kabupaten Bojonegoro.',
    sections: [
      {
        title: 'Mengenai Data Spasial',
        body: 'Sistem Geospasial Melarosa menyajikan pemetaan digital untuk batas wilayah administrasi desa, batas wilayah kecamatan, serta data jaringan jalan poros desa di Kabupaten Bojonegoro.',
      },
      {
        title: 'Akses Peta Publik',
        body: 'Masyarakat umum dan instansi terkait dapat mengakses peta interaktif secara langsung tanpa perlu proses login melalui navigasi Peta Interaktif.',
      },
    ],
  },
}));

const pageData = computed(() => {
  const normalized = slug.value.toLowerCase();
  if (pageDatabase.value[normalized]) {
    return pageDatabase.value[normalized];
  }
  return null;
});

const pageTitle = computed(() => {
  if (pageData.value?.title) return pageData.value.title;
  return currentHeader.value.title || `Halaman ${slug.value}`;
});

const pageDescription = computed(() => {
  if (pageData.value?.description) return pageData.value.description;
  return currentHeader.value.description || '';
});

useSeoMeta({
  title: () => `${pageTitle.value} | Melarosa GIS`,
  description: () => pageDescription.value,
});
</script>

<template>
  <PublicPageTemplate
    :title="pageTitle"
    :description="pageDescription"
    :empty="!pageData"
    empty-title="Konten Halaman Belum Tersedia"
    empty-description="Halaman ini telah terdaftar dalam sistem navigasi publik, namun konten artikel detail belum diunggah."
    icon="i-lucide-file-text"
  >
    <template #actions>
      <UButton
        label="Buka Peta"
        to="/maps"
        icon="i-lucide-map"
        color="primary"
        variant="solid"
        size="sm"
        class="cursor-pointer min-h-[44px]"
      />
    </template>

    <div v-if="pageData" class="space-y-6">
      <div
        v-for="(section, idx) in pageData.sections"
        :key="idx"
        class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 p-6 shadow-xs"
      >
        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">
          {{ section.title }}
        </h2>
        <p class="mt-2 text-sm sm:text-base text-slate-600 dark:text-slate-300 leading-relaxed">
          {{ section.body }}
        </p>
      </div>
    </div>
  </PublicPageTemplate>
</template>

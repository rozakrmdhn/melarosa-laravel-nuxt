<script setup lang="ts">
import { useGeoparkSites } from '~/composables/useGeoparkSites';
import LandingHero from '~/components/landing/LandingHero.vue';
import RegionalMetrics from '~/components/landing/RegionalMetrics.vue';
import DistrictExplorer from '~/components/landing/DistrictExplorer.vue';
import GeoparkEditorial from '~/components/landing/GeoparkEditorial.vue';
import SpatialDataStory from '~/components/landing/SpatialDataStory.vue';
import MapGuide from '~/components/landing/MapGuide.vue';
import LandingCTA from '~/components/landing/LandingCTA.vue';

useSeoMeta({
  title: 'Melarosa Bojonegoro: Observatorium Spasial Pembangunan Desa & Geopark',
  description: 'Pantau 1.187 ruas jalan poros desa, sebaran pembangunan di 28 kecamatan dan 430 desa, serta 31 situs Geopark Nasional Kabupaten Bojonegoro.',
});

// Data Geopark terverifikasi Kabupaten Bojonegoro (31 situs)
const {
  sites: geoparkSites,
  loading: geoparkLoading,
  error: geoparkError,
  geologicalSites,
  biologicalSites,
  culturalSites,
  fetchLiveSites,
} = useGeoparkSites();
</script>

<template>
  <main class="w-full flex flex-col bg-[#F7F8F5] dark:bg-[#070b14] text-[#17251F] dark:text-slate-100 selection:bg-[#176B4D] selection:text-white">
    <!-- 01. Hero Editorial + Peta Wilayah -->
    <LandingHero
      :geopark-sites="geoparkSites"
    />

    <!-- 02. Angka Bojonegoro dalam Satu Garis Informasi -->
    <RegionalMetrics />

    <!-- 03. Jelajahi Wilayah (Direktori Atlas 28 Kecamatan) -->
    <DistrictExplorer />

    <!-- 04. Cerita Geopark Bojonegoro (Galeri Editorial 31 Situs) -->
    <GeoparkEditorial
      :sites="geoparkSites"
      :geological-sites="geologicalSites"
      :biological-sites="biologicalSites"
      :cultural-sites="culturalSites"
      :loading="geoparkLoading"
      :error="geoparkError"
      @refresh="fetchLiveSites(true)"
    />

    <!-- 05. Satu Peta, Banyak Informasi (Penumpukan Lapisan Tematik) -->
    <SpatialDataStory />

    <!-- 06. Panduan Eksplorasi Interaktif -->
    <MapGuide />

    <!-- 07. Ajakan Penutup (CTA) -->
    <LandingCTA />
  </main>
</template>

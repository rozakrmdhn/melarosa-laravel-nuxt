<script setup lang="ts">
import type { BasemapPreviewStyle } from '~/types/basemap';

const props = withDefaults(
  defineProps<{
    previewStyle?: BasemapPreviewStyle | string;
    thumbnailUrl?: string | null;
    alt?: string;
  }>(),
  {
    previewStyle: 'esri',
    thumbnailUrl: null,
    alt: 'Pratinjau basemap',
  },
);

const imgFailed = ref(false);
watch(() => props.thumbnailUrl, () => {
  imgFailed.value = false;
});
</script>

<template>
  <div class="w-full h-full relative overflow-hidden">
    <img
      v-if="thumbnailUrl && !imgFailed"
      :src="thumbnailUrl"
      :alt="alt"
      class="w-full h-full object-cover"
      @error="imgFailed = true"
    >

    <!-- Esri / Topo -->
    <svg
      v-else-if="previewStyle === 'esri' || previewStyle === 'topo'"
      viewBox="0 0 64 48"
      preserveAspectRatio="xMidYMid slice"
      class="w-full h-full"
      aria-hidden="true"
    >
      <rect width="64" height="48" fill="#eef4ea" />
      <path d="M-4 32 C 12 22, 26 38, 42 26 S 60 20, 68 28 L 68 48 L -4 48 Z" fill="#cde1c3" />
      <path d="M-4 20 C 16 14, 30 28, 48 16 S 62 12, 68 18" fill="none" stroke="#9ec28d" stroke-width="1.5" />
      <path d="M8 48 C 16 32, 24 22, 34 0" fill="none" stroke="#60a5fa" stroke-width="3" />
      <path d="M0 16 L 64 36" fill="none" stroke="#e2a856" stroke-width="1.8" />
    </svg>

    <!-- RBI -->
    <svg
      v-else-if="previewStyle === 'rbi'"
      viewBox="0 0 64 48"
      preserveAspectRatio="xMidYMid slice"
      class="w-full h-full"
      aria-hidden="true"
    >
      <rect width="64" height="48" fill="#f7f3e3" />
      <path d="M0 12 C 20 8, 40 18, 64 10" fill="none" stroke="#d97706" stroke-width="1.2" stroke-dasharray="3 2" />
      <path d="M0 30 C 18 24, 38 34, 64 26" fill="none" stroke="#3b82f6" stroke-width="2.5" />
      <path d="M18 0 L 28 48" fill="none" stroke="#dc2626" stroke-width="1.8" />
      <rect x="38" y="16" width="12" height="8" rx="1.5" fill="#d1fae5" stroke="#059669" stroke-width="1" />
    </svg>

    <!-- OSM -->
    <svg
      v-else-if="previewStyle === 'osm'"
      viewBox="0 0 64 48"
      preserveAspectRatio="xMidYMid slice"
      class="w-full h-full"
      aria-hidden="true"
    >
      <rect width="64" height="48" fill="#f3f1ea" />
      <rect x="4" y="6" width="18" height="14" rx="2" fill="#d9edd2" />
      <rect x="40" y="28" width="20" height="16" rx="2" fill="#d9edd2" />
      <path d="M0 24 L 64 24" fill="none" stroke="#f59e0b" stroke-width="3" />
      <path d="M28 0 L 28 48" fill="none" stroke="#ffffff" stroke-width="3.5" />
      <path d="M28 0 L 28 48" fill="none" stroke="#cbd5e1" stroke-width="1" />
      <path d="M10 48 L 54 0" fill="none" stroke="#ffffff" stroke-width="2" />
    </svg>

    <!-- Satellite -->
    <svg
      v-else-if="previewStyle === 'satellite'"
      viewBox="0 0 64 48"
      preserveAspectRatio="xMidYMid slice"
      class="w-full h-full"
      aria-hidden="true"
    >
      <rect width="64" height="48" fill="#1b3022" />
      <ellipse cx="18" cy="16" rx="20" ry="12" fill="#274330" />
      <ellipse cx="48" cy="34" rx="22" ry="14" fill="#13261b" />
      <path d="M0 36 C 20 28, 36 20, 64 14" fill="none" stroke="#365314" stroke-width="5" />
      <path d="M14 0 C 20 18, 28 32, 22 48" fill="none" stroke="#1e3a5f" stroke-width="4" />
    </svg>

    <!-- Aerial / Hybrid -->
    <svg
      v-else-if="previewStyle === 'aerial'"
      viewBox="0 0 64 48"
      preserveAspectRatio="xMidYMid slice"
      class="w-full h-full"
      aria-hidden="true"
    >
      <rect width="64" height="48" fill="#1e2f23" />
      <rect x="8" y="8" width="14" height="10" rx="1" fill="#3f5242" />
      <rect x="38" y="24" width="16" height="12" rx="1" fill="#4b5e4c" />
      <path d="M0 24 L 64 20" fill="none" stroke="#facc15" stroke-width="2" />
      <path d="M28 0 L 32 48" fill="none" stroke="#ffffff" stroke-width="1.5" stroke-opacity="0.85" />
      <rect x="34" y="10" width="18" height="4" rx="2" fill="#0f172a" fill-opacity="0.7" />
    </svg>

    <!-- Light / Positron -->
    <svg
      v-else-if="previewStyle === 'light'"
      viewBox="0 0 64 48"
      preserveAspectRatio="xMidYMid slice"
      class="w-full h-full"
      aria-hidden="true"
    >
      <rect width="64" height="48" fill="#f8fafc" />
      <path d="M0 20 L 64 28" fill="none" stroke="#cbd5e1" stroke-width="2.5" />
      <path d="M26 0 L 22 48" fill="none" stroke="#e2e8f0" stroke-width="3" />
      <rect x="38" y="6" width="16" height="10" rx="2" fill="#e2e8f0" />
    </svg>

    <!-- Dark -->
    <svg
      v-else-if="previewStyle === 'dark'"
      viewBox="0 0 64 48"
      preserveAspectRatio="xMidYMid slice"
      class="w-full h-full"
      aria-hidden="true"
    >
      <rect width="64" height="48" fill="#0f172a" />
      <path d="M0 22 L 64 28" fill="none" stroke="#334155" stroke-width="2.5" />
      <path d="M28 0 L 24 48" fill="none" stroke="#1e293b" stroke-width="3.5" />
      <path d="M8 44 L 56 6" fill="none" stroke="#38bdf8" stroke-opacity="0.45" stroke-width="1.4" />
    </svg>

    <!-- None / Blank Canvas -->
    <svg
      v-else
      viewBox="0 0 64 48"
      preserveAspectRatio="xMidYMid slice"
      class="w-full h-full"
      aria-hidden="true"
    >
      <rect width="64" height="48" fill="#ffffff" />
      <path d="M0 16 H64 M0 32 H64 M21 0 V48 M43 0 V48" stroke="#e2e8f0" stroke-width="1" stroke-dasharray="2 2" />
    </svg>
  </div>
</template>

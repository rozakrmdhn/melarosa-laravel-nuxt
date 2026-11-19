<script setup lang="ts">
import { computed } from "vue";
import type { MartinLegendSwatch } from "~/utils/mvt-legend";

const props = defineProps<{
  swatch: MartinLegendSwatch;
}>();

const svgDashArray = computed(() => {
  const dash = props.swatch.dashArray;
  if (!dash || dash.length === 0) return undefined;
  return dash.map((n) => Math.max(1.5, Math.round(n * 0.55))).join(" ");
});

const clampedStrokeWidth = computed(() => {
  const raw = Number(props.swatch.strokeWidth || 2);
  return Math.min(4.5, Math.max(1.5, raw));
});
</script>

<template>
  <svg
    viewBox="0 0 28 18"
    class="w-7 h-[18px] shrink-0 overflow-visible"
    aria-hidden="true"
  >
    <!-- Line / Boundary Linear Swatch -->
    <template v-if="swatch.geomType === 'line'">
      <line
        v-if="swatch.casingColor"
        x1="2"
        y1="9"
        x2="26"
        y2="9"
        :stroke="swatch.casingColor"
        :stroke-width="clampedStrokeWidth + 3"
        stroke-linecap="round"
      />
      <line
        x1="2"
        y1="9"
        x2="26"
        y2="9"
        :stroke="swatch.strokeColor"
        :stroke-width="clampedStrokeWidth"
        :stroke-dasharray="svgDashArray"
        stroke-linecap="round"
      />
    </template>

    <!-- Polygon Area Swatch -->
    <template v-else-if="swatch.geomType === 'polygon'">
      <rect
        v-if="swatch.casingColor"
        x="2"
        y="2"
        width="24"
        height="14"
        rx="3"
        fill="none"
        :stroke="swatch.casingColor"
        :stroke-width="clampedStrokeWidth + 1.5"
      />
      <rect
        x="2"
        y="2"
        width="24"
        height="14"
        rx="3"
        :fill="swatch.fillColor || 'rgba(59, 130, 246, 0.14)'"
        :stroke="swatch.strokeColor"
        :stroke-width="Math.min(2.5, clampedStrokeWidth)"
        :stroke-dasharray="svgDashArray"
      />
    </template>

    <!-- Point Marker Swatch -->
    <template v-else>
      <circle
        cx="14"
        cy="9"
        :r="Math.min(7, Math.max(4, swatch.pointRadius || 5))"
        :fill="swatch.strokeColor"
        :stroke="swatch.pointBorderColor || '#ffffff'"
        stroke-width="2"
      />
    </template>
  </svg>
</template>

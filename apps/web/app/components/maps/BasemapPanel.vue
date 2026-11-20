<script setup lang="ts">
import type { BasemapKey } from "~/types/map-layers";
import BasemapSwitcher from "~/components/common/BasemapSwitcher.vue";

const props = withDefaults(
  defineProps<{
    currentBasemap?: BasemapKey;
    isMobile?: boolean;
    isMobileSheetOpen?: boolean;
  }>(),
  {
    currentBasemap: "esri",
    isMobile: false,
    isMobileSheetOpen: false,
  }
);

const emit = defineEmits<{
  (e: "update:currentBasemap", val: BasemapKey): void;
  (e: "open-mobile-sheet"): void;
}>();

const selectedModel = computed<string>({
  get: () => props.currentBasemap,
  set: (val) => emit("update:currentBasemap", val as BasemapKey),
});
</script>

<template>
  <BasemapSwitcher
    v-model="selectedModel"
    variant="interactive"
    engine="maplibre"
    :allow-none="true"
    :is-mobile="isMobile"
    :is-mobile-sheet-open="isMobileSheetOpen"
    @open-mobile-sheet="emit('open-mobile-sheet')"
  />
</template>

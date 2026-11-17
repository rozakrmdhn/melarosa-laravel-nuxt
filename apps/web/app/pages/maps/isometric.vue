<script setup lang="ts">
import { onMounted, ref } from 'vue'
import IsometricPixelMap from '~/components/maps/IsometricPixelMap.vue'
import MaplibreRdtr3DMap from '~/components/maps/MaplibreRdtr3DMap.vue'

definePageMeta({
  layout: false,
  fullBleed: true,
})

useHead({
  htmlAttrs: {
    class: 'overflow-hidden h-full',
  },
  title: 'Peta 3D RDTR & Isometrik Kota Bojonegoro | Melarosa GIS',
  meta: [
    {
      name: 'description',
      content:
        'Peta 3D RDTR 1:5.000 Kecamatan Bojonegoro dengan MapLibre GL JS: bangunan eksisting OSM dan generate RDTR, lebar jalan dan rel KAI, Bengawan Solo, RTH pohon 3D, serta area pertanian.',
    },
  ],
})

const isMapsReady = useState<boolean>('melarosa_maps_ready', () => false)
const viewMode = ref<'rdtr3d' | 'pixel'>('rdtr3d')

onMounted(() => {
  isMapsReady.value = true
})
</script>

<template>
  <section
    class="relative h-full w-full overflow-hidden bg-[#EFEAE0]"
    aria-label="Tampilan Peta 3D RDTR dan Piksel Isometrik Bojonegoro"
  >
    <MaplibreRdtr3DMap
      v-if="viewMode === 'rdtr3d'"
      show-pixel-switch
      @switch-pixel="viewMode = 'pixel'"
    />
    <IsometricPixelMap v-else>
      <template #mode-switch>
        <button
          type="button"
          class="inline-flex min-h-[44px] cursor-pointer items-center gap-1.5 rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-600 focus-visible:ring-2 focus-visible:ring-emerald-400 focus-visible:outline-none"
          @click="viewMode = 'rdtr3d'"
        >
          <span>Peta 3D RDTR</span>
        </button>
      </template>
    </IsometricPixelMap>
  </section>
</template>

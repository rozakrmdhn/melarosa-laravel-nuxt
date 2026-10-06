<script setup lang="ts">
interface DatasetItem {
  id: string;
  name: string;
  title: string;
  protocol: string;
  url: string;
  layer_name: string | null;
  attribution: string | null;
  description: string | null;
  source_type: string | null;
  opacity: number;
  order: number;
  is_active: boolean;
  default_visible: boolean;
  created_at?: string;
  updated_at?: string;
  wmsUrl?: string;
  wmsLayerName?: string;
  apiEndpoint?: string;
}

const props = withDefaults(
  defineProps<{
    open?: boolean;
    layersData?: any;
    activeLayerIds?: string[];
  }>(),
  {
    open: false,
    layersData: null,
    activeLayerIds: () => [],
  }
);

const emit = defineEmits<{
  (e: "update:open", value: boolean): void;
  (e: "select-dataset", dataset: DatasetItem): void;
}>();

const isOpen = computed({
  get: () => props.open,
  set: (val) => emit("update:open", val),
});

function isLayerActive(id: string): boolean {
  return props.activeLayerIds.includes(id);
}

const searchQuery = ref("");
const selectedProtocol = ref("Semua");
const copiedId = ref<string | null>(null);

// Ambil data layer asli langsung dari API layersData tanpa fabrikasi metadata
const allDatasets = computed<DatasetItem[]>(() => {
  const dynamic = props.layersData?.data || (Array.isArray(props.layersData) ? props.layersData : null);
  if (!Array.isArray(dynamic)) return [];

  return dynamic
    .filter((item: any) => item && (item.name || item.layer_name))
    .map((item: any) => ({
      id: String(item.id),
      name: item.name || item.layer_name,
      title: item.name || item.layer_name,
      protocol: (item.protocol || "").toUpperCase(),
      url: item.url || "",
      layer_name: item.layer_name || null,
      attribution: item.attribution || null,
      description: item.description || null,
      source_type: item.source_type || null,
      opacity: typeof item.opacity === "number" ? item.opacity : 1,
      order: typeof item.order === "number" ? item.order : 0,
      is_active: Boolean(item.is_active),
      default_visible: Boolean(item.default_visible),
      created_at: item.created_at,
      updated_at: item.updated_at,
      wmsUrl: item.url,
      wmsLayerName: item.layer_name,
      apiEndpoint: item.url,
    }));
});

// Daftar filter protokol yang ada pada dataset
const protocolFilters = computed(() => {
  const set = new Set<string>();
  for (const d of allDatasets.value) {
    if (d.protocol) set.add(d.protocol);
  }
  return ["Semua", ...Array.from(set)];
});

const filteredDatasets = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  const proto = selectedProtocol.value;

  return allDatasets.value.filter((d) => {
    const matchProtocol = proto === "Semua" || d.protocol === proto;
    if (!matchProtocol) return false;

    if (!q) return true;
    return (
      (d.name && d.name.toLowerCase().includes(q)) ||
      (d.layer_name && d.layer_name.toLowerCase().includes(q)) ||
      (d.attribution && d.attribution.toLowerCase().includes(q)) ||
      (d.description && d.description.toLowerCase().includes(q)) ||
      (d.protocol && d.protocol.toLowerCase().includes(q)) ||
      (d.source_type && d.source_type.toLowerCase().includes(q))
    );
  });
});

function handleSelect(item: DatasetItem) {
  emit("select-dataset", item);
  isOpen.value = false;
}

async function copyEndpoint(item: DatasetItem) {
  const text = item.url || item.apiEndpoint || `${window.location.origin}/maps?layer=${item.id}`;
  try {
    await navigator.clipboard.writeText(text);
    copiedId.value = item.id;
    setTimeout(() => {
      if (copiedId.value === item.id) copiedId.value = null;
    }, 2000);
  } catch (err) {
    console.error("Gagal menyalin tautan", err);
  }
}
</script>

<template>
  <UModal
    v-model:open="isOpen"
    title="Katalog Data Geospasial Kabupaten Bojonegoro"
    description="Pusat inventaris dataset spasial terbuka, batas administrasi, infrastruktur jalan, dan layer tematik WMS/WFS."
    :ui="{
      content: 'sm:max-w-4xl w-[calc(100vw-2rem)] max-h-[90vh] bg-white dark:bg-[#09111e] border border-slate-200/90 dark:border-white/10 rounded-2xl md:rounded-3xl shadow-2xl flex flex-col overflow-hidden',
      header: 'px-5 py-4 border-b border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.02]',
      body: 'p-0 flex-1 overflow-y-auto',
      footer: 'px-5 py-3 border-t border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.02]',
    }"
  >
    <template #body>
      <div class="flex flex-col h-full divide-y divide-slate-100 dark:divide-white/5">
        <!-- Search and Filter Bar -->
        <div class="p-4 sm:p-5 space-y-3 bg-white dark:bg-[#09111e]">
          <div class="relative">
            <UIcon
              name="i-lucide-search"
              class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-slate-400 dark:text-slate-500"
            />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari dataset, shapefile, nama dinas, atau kata kunci spasial..."
              class="w-full h-11 pl-10 pr-10 rounded-xl bg-slate-50 dark:bg-[#0b0f19] border border-slate-200 dark:border-white/10 text-xs sm:text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-hidden focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
            />
            <button
              v-if="searchQuery"
              type="button"
              class="absolute right-3 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
              aria-label="Bersihkan pencarian"
              @click="searchQuery = ''"
            >
              <UIcon name="i-lucide-x" class="size-3.5" />
            </button>
          </div>

          <!-- Protocol Chips Filter -->
          <div class="flex items-center gap-1.5 overflow-x-auto pb-1 no-scrollbar text-xs">
            <button
              v-for="proto in protocolFilters"
              :key="proto"
              type="button"
              class="shrink-0 px-3 py-1.5 rounded-xl font-medium transition-all cursor-pointer min-h-[36px] flex items-center gap-1.5"
              :class="selectedProtocol === proto
                ? 'bg-blue-600 text-white shadow-xs font-semibold'
                : 'bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/10'"
              @click="selectedProtocol = proto"
            >
              <span>{{ proto }}</span>
              <span
                v-if="proto === 'Semua'"
                class="px-1.5 py-0.2 rounded-md text-[10px]"
                :class="selectedProtocol === proto ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-white/10 text-slate-500 dark:text-slate-400'"
              >
                {{ allDatasets.length }}
              </span>
            </button>
          </div>
        </div>

        <!-- Dataset Grid List -->
        <div class="p-4 sm:p-5 flex-1 overflow-y-auto space-y-3 max-h-[55vh]">
          <div v-if="filteredDatasets.length === 0" class="py-12 text-center space-y-2">
            <div class="size-12 rounded-2xl bg-slate-100 dark:bg-white/5 text-slate-400 mx-auto flex items-center justify-center">
              <UIcon name="i-lucide-file-question" class="size-6" />
            </div>
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
              Tidak ada dataset yang cocok
            </p>
            <p class="text-xs text-slate-400">
              Coba gunakan kata kunci pencarian lain atau pilih filter Semua.
            </p>
          </div>

          <div
            v-for="item in filteredDatasets"
            :key="item.id"
            class="group p-4 rounded-2xl border border-slate-200/80 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] hover:border-blue-500/50 hover:shadow-md transition-all duration-200 flex flex-col md:flex-row items-start md:items-center justify-between gap-4"
          >
            <!-- Left Info -->
            <div class="flex items-start gap-3.5 min-w-0 flex-1">
              <div
                class="size-10 rounded-2xl flex items-center justify-center shrink-0 mt-0.5 bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 shadow-xs"
              >
                <UIcon name="i-lucide-layers" class="size-5" />
              </div>

              <div class="space-y-1.5 min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-1.5">
                  <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                    {{ item.name }}
                  </h3>
                  <UBadge
                    v-if="item.protocol"
                    :label="item.protocol.toUpperCase()"
                    size="xs"
                    color="primary"
                    variant="subtle"
                    class="text-[10px] px-2 py-0.5 rounded-lg font-mono uppercase"
                  />
                  <UBadge
                    v-if="item.source_type"
                    :label="item.source_type"
                    size="xs"
                    color="neutral"
                    variant="subtle"
                    class="text-[10px] px-2 py-0.5 rounded-lg capitalize"
                  />
                </div>

                <p v-if="item.description" class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed line-clamp-2">
                  {{ item.description }}
                </p>

                <!-- Metadata Row Berdasarkan Layer Item Asli -->
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[11px] text-slate-500 dark:text-slate-400 pt-0.5">
                  <!-- Nama Teknis Layer -->
                  <span v-if="item.layer_name" class="flex items-center gap-1 font-mono text-slate-700 dark:text-slate-300">
                    <UIcon name="i-lucide-tag" class="size-3.5 text-slate-400 shrink-0" />
                    <span class="truncate max-w-[220px]" :title="item.layer_name">{{ item.layer_name }}</span>
                  </span>

                  <!-- Atribusi / Instansi Sumber -->
                  <span v-if="item.attribution" class="flex items-center gap-1">
                    <UIcon name="i-lucide-building-2" class="size-3.5 text-slate-400 shrink-0" />
                    <span class="truncate max-w-[200px]" :title="item.attribution">{{ item.attribution }}</span>
                  </span>

                  <!-- URL Layanan / Endpoint -->
                  <span v-if="item.url" class="flex items-center gap-1 font-mono text-slate-500">
                    <UIcon name="i-lucide-link-2" class="size-3.5 text-slate-400 shrink-0" />
                    <span class="truncate max-w-[220px]" :title="item.url">{{ item.url }}</span>
                  </span>

                  <!-- Tanggal Pembaruan -->
                  <span v-if="item.updated_at" class="flex items-center gap-1 font-mono text-slate-400">
                    <UIcon name="i-lucide-clock" class="size-3.5 text-slate-400 shrink-0" />
                    <span>{{ new Date(item.updated_at).toLocaleDateString("id-ID") }}</span>
                  </span>
                </div>
              </div>
            </div>

            <!-- Right Actions -->
            <div class="flex items-center gap-2 w-full md:w-auto shrink-0 pt-2 md:pt-0 border-t md:border-t-0 border-slate-100 dark:border-white/5">
              <UButton
                :icon="copiedId === item.id ? 'i-lucide-check' : 'i-lucide-link'"
                :label="copiedId === item.id ? 'Tersalin' : 'Salin Tautan'"
                size="xs"
                color="neutral"
                variant="ghost"
                class="rounded-xl min-h-[44px] text-xs cursor-pointer flex-1 md:flex-none justify-center"
                @click="copyEndpoint(item)"
              />

              <UButton
                :icon="isLayerActive(item.id) ? 'i-lucide-check-circle-2' : 'i-lucide-plus-circle'"
                :label="isLayerActive(item.id) ? 'Sudah di Panel' : 'Tambahkan ke Peta'"
                size="xs"
                :color="isLayerActive(item.id) ? 'neutral' : 'primary'"
                :variant="isLayerActive(item.id) ? 'subtle' : 'solid'"
                class="rounded-xl min-h-[44px] text-xs font-semibold cursor-pointer shadow-xs flex-1 md:flex-none justify-center"
                @click="handleSelect(item)"
              />
            </div>
          </div>
        </div>
      </div>
    </template>

    <template #footer>
      <div class="flex items-center justify-between w-full">
        <div class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
          <UIcon name="i-lucide-database" class="size-3.5 text-blue-500" />
          <span>Katalog Satu Data Spasial Pemerintah Kabupaten Bojonegoro</span>
        </div>

        <UButton
          label="Tutup Katalog"
          size="sm"
          color="neutral"
          variant="subtle"
          class="rounded-xl cursor-pointer text-xs font-medium"
          @click="isOpen = false"
        />
      </div>
    </template>
  </UModal>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>

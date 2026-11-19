<script lang="ts">
export interface KugiMeta {
  fcode: string;
  typeName?: string;
  aliases?: string;
  definition?: string;
  producer?: string;
  attributes: Record<string, string>;
}

// Module-level persistent cache across all mounts, unmounts, and tab switches
const globalKugiCache = new Map<string, KugiMeta>();
const inFlightKugiPromises = new Map<string, Promise<KugiMeta | null>>();
</script>

<script setup lang="ts">
import { $http } from "~/utils/helpers";

export interface IdentifiedFeature {
  id?: string;
  rawFeatureId?: string | null;
  layerId: string;
  layerName?: string | null;
  selectionKey?: string | null;
  layerTitle: string;
  layerAttribution?: string | null;
  layerInstansi?: string | null;
  geometryType?: string;
  properties: Record<string, any>;
  coordinate?: [number, number];
  extent?: [number, number, number, number];
  geojsonGeometry?: any;
  olGeometry?: any;
  layerIndex?: number;
}

const props = withDefaults(
  defineProps<{
    features?: IdentifiedFeature[];
    loading?: boolean;
    error?: string | null;
    selectedIndex?: number;
    hasPoint?: boolean;
  }>(),
  {
    features: () => [],
    loading: false,
    error: null,
    selectedIndex: undefined,
    hasPoint: false,
  }
);

const emit = defineEmits<{
  (e: "select-feature", feature: IdentifiedFeature): void;
  (e: "update:selected-index", index: number): void;
}>();

const internalSelectedIndex = ref(0);
const activeIndex = computed({
  get: () => (props.selectedIndex !== undefined ? props.selectedIndex : internalSelectedIndex.value),
  set: (val: number) => {
    internalSelectedIndex.value = val;
    emit("update:selected-index", val);
  },
});

const searchQuery = ref("");
const copiedKey = ref<string | null>(null);
let copiedTimer: ReturnType<typeof setTimeout> | null = null;

const activeKugiMeta = ref<KugiMeta | null>(null);
const isLoadingKugi = ref(false);

// Reset indeks aktif & saringan pencarian jika daftar fitur berganti
watch(
  () => props.features,
  () => {
    searchQuery.value = "";
    activeIndex.value = 0;
  }
);

const currentFeature = computed<IdentifiedFeature | null>(() => {
  if (!props.features || props.features.length === 0) return null;
  return props.features[activeIndex.value] || props.features[0] || null;
});

// Beritahu komponen induk saat fitur aktif berubah
watch(
  currentFeature,
  (feat) => {
    if (feat) {
      emit("select-feature", feat);
    }
  },
  { immediate: true }
);

// Deteksi FCODE dari properti fitur secara dinamis (case-insensitive)
const currentFcode = computed<string | null>(() => {
  if (!currentFeature.value?.properties) return null;
  const p = currentFeature.value.properties;
  for (const [k, v] of Object.entries(p)) {
    if (k.toUpperCase() === "FCODE" && v) {
      const codeStr = String(v).trim();
      if (codeStr && codeStr !== "-") return codeStr;
    }
  }
  return null;
});

// Ambil definisi kamus KUGI saat FCODE terdeteksi (dengan cache persisten & deduplikasi)
watch(
  currentFcode,
  async (fcode) => {
    if (!fcode) {
      activeKugiMeta.value = null;
      return;
    }

    const upperCode = fcode.toUpperCase();
    if (globalKugiCache.has(upperCode)) {
      activeKugiMeta.value = globalKugiCache.get(upperCode) || null;
      return;
    }

    if (inFlightKugiPromises.has(upperCode)) {
      try {
        const meta = await inFlightKugiPromises.get(upperCode);
        if (currentFcode.value?.toUpperCase() === upperCode) {
          activeKugiMeta.value = meta || null;
        }
      } catch {}
      return;
    }

    isLoadingKugi.value = true;
    const fetchPromise = (async () => {
      try {
        const res = await $http<{ ok: boolean; code: string; data: any[] }>(
          "kugi/feature-type",
          { query: { code: upperCode } }
        );

        const items = Array.isArray(res?.data) ? res.data : [];
        if (items.length > 0) {
          const first = items[0];
          const attributes: Record<string, string> = {};

          for (const item of items) {
            if (item?.faCode && item?.ptDefinition) {
              attributes[String(item.faCode).toUpperCase()] = String(item.ptDefinition).trim();
            }
          }

          const meta: KugiMeta = {
            fcode: upperCode,
            typeName: first.typeName || undefined,
            aliases: first.aliases || undefined,
            definition: first.definition || undefined,
            producer: first.fcProducer || undefined,
            attributes,
          };

          globalKugiCache.set(upperCode, meta);
          return meta;
        }
        return null;
      } catch (err) {
        console.warn("Gagal memuat kamus KUGI:", err);
        return null;
      } finally {
        inFlightKugiPromises.delete(upperCode);
        isLoadingKugi.value = false;
      }
    })();

    inFlightKugiPromises.set(upperCode, fetchPromise);
    const meta = await fetchPromise;
    if (currentFcode.value?.toUpperCase() === upperCode) {
      activeKugiMeta.value = meta;
    }
  },
  { immediate: true }
);


// Resolusi label atribut: prioritas ke kamus KUGI resmi BIG, lalu kamus lokal, lalu snake_case
function resolveAttributeLabel(key: string): { label: string; isKugi: boolean } {
  const upperKey = key.toUpperCase();

  // 1. Cek kamus KUGI resmi dari FCODE fitur ini
  if (activeKugiMeta.value?.attributes[upperKey]) {
    return {
      label: activeKugiMeta.value.attributes[upperKey],
      isKugi: true,
    };
  }

  // 2. Kamus alias umum GIS Indonesia / Palapa Geoportal
  const dictionary: Record<string, string> = {
    NAMOBJ: "Nama Wilayah / Objek",
    REMARK: "Keterangan",
    WADMKC: "Kecamatan",
    WADMKD: "Desa / Kelurahan",
    WADMKK: "Kabupaten / Kota",
    WADMPR: "Provinsi",
    LUASWH: "Luas Wilayah",
    TIPADM: "Tipe Administrasi",
    SRS_ID: "Referensi Peta",
    METADATA: "Sumber Data",
    UUPP: "Dasar Hukum / Delineasi",
    NAMA_RUAS: "Nama Ruas Jalan",
    PANJANG_KM: "Panjang Ruas",
    LEBAR_M: "Lebar Jalan",
    KONDISI: "Kondisi Jalan",
    TIPE_PERM: "Tipe Perkerasan",
    STATUS_RUAS: "Status Ruas",
    KODE_DESA: "Kode Wilayah",
    KDCPUM: "Kode Kecamatan",
    KDEPUM: "Kode Desa",
    FCODE: "Kode Unsur KUGI",
  };

  if (dictionary[upperKey]) {
    return {
      label: dictionary[upperKey],
      isKugi: false,
    };
  }

  // 3. Fallback pemformatan huruf alami dari snake_case atau camelCase
  const formatted = key
    .replace(/_/g, " ")
    .replace(/([a-z])([A-Z])/g, "$1 $2")
    .replace(/\b\w/g, (char) => char.toUpperCase());

  return {
    label: formatted,
    isKugi: false,
  };
}

// Daftar seluruh properti terformat (sebelum saringan pencarian)
const excludedKeys = new Set([
  "bbox",
  "geometry",
  "the_geom",
  "geom",
  "fid",
  "ogc_fid",
  "objectid",
  "fcode",
  "lcode",
  "srs_id",
  "shape_leng",
  "shape_area",
  "tipadm",
  "uupp",
  "id_segmen",
  "id_ruas",
  "id_desa",
  "id_kecamatan",
  "kdcpum",
  "kdepum",
]);

const allFormattedProperties = computed(() => {
  if (!currentFeature.value) return [];
  const rawProps = currentFeature.value.properties || {};

  return Object.entries(rawProps)
    .filter(([key, val]) => {
      if (excludedKeys.has(key.toLowerCase())) return false;
      if (val === null || val === undefined || val === "") return false;
      return true;
    })
    .map(([key, val]) => {
      const { label, isKugi } = resolveAttributeLabel(key);

      let formattedVal: string;
      if (typeof val === "number") {
        formattedVal = Number.isInteger(val)
          ? val.toLocaleString("id-ID")
          : val.toLocaleString("id-ID", { maximumFractionDigits: 4 });
      } else if (typeof val === "boolean") {
        formattedVal = val ? "Ya" : "Tidak";
      } else if (typeof val === "object") {
        formattedVal = JSON.stringify(val);
      } else {
        formattedVal = String(val);
      }

      return {
        key,
        label,
        isKugi,
        value: formattedVal,
        raw: val,
      };
    });
});

const formattedProperties = computed(() => {
  const query = searchQuery.value.trim().toLowerCase();
  if (!query) return allFormattedProperties.value;
  return allFormattedProperties.value.filter(
    (item) =>
      item.key.toLowerCase().includes(query) ||
      item.label.toLowerCase().includes(query) ||
      item.value.toLowerCase().includes(query)
  );
});

function fallbackCopy(text: string) {
  const textArea = document.createElement("textarea");
  textArea.value = text;
  textArea.style.position = "fixed";
  textArea.style.opacity = "0";
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();
  try {
    document.execCommand("copy");
  } catch (err) {
    console.warn("Gagal menyalin teks:", err);
  }
  document.body.removeChild(textArea);
}

function copyValue(key: string, val: string) {
  if (navigator?.clipboard?.writeText && window.isSecureContext) {
    navigator.clipboard.writeText(val).catch(() => fallbackCopy(val));
  } else {
    fallbackCopy(val);
  }
  copiedKey.value = key;
  if (copiedTimer) clearTimeout(copiedTimer);
  copiedTimer = setTimeout(() => {
    copiedKey.value = null;
  }, 1800);
}
</script>

<template>
  <div class="space-y-3">
    <!-- ═══ 1. STATE MEMUAT (LOADING) ═══════════════════════════════════════ -->
    <div
      v-if="loading"
      key="loading"
      class="p-3.5 rounded-2xl border border-slate-200/80 dark:border-white/10 bg-slate-50/80 dark:bg-white/[0.03] space-y-3"
    >
      <div class="flex items-center gap-3">
        <UIcon name="i-ion-refresh-outline" class="size-4.5 text-blue-600 dark:text-blue-400 animate-spin shrink-0" />
        <div class="min-w-0 flex-1">
          <p class="text-xs font-bold text-slate-900 dark:text-slate-100">
            Memeriksa informasi lokasi...
          </p>
          <p class="text-xs text-slate-600 dark:text-slate-400 truncate mt-0.5">
            Mengambil atribut data peta Bojonegoro
          </p>
        </div>
      </div>

      <div class="space-y-2 pt-2 border-t border-slate-200/70 dark:border-white/10">
        <div class="h-3 bg-slate-200/80 dark:bg-white/10 rounded-md w-3/4 animate-pulse" />
        <div class="h-3 bg-slate-200/60 dark:bg-white/5 rounded-md w-1/2 animate-pulse" />
      </div>
    </div>

    <!-- ═══ 2. STATE ERROR ══════════════════════════════════════════════════ -->
    <div
      v-else-if="error"
      key="error"
      class="p-3.5 rounded-2xl border border-red-200/80 dark:border-red-900/50 bg-red-50/70 dark:bg-red-950/25 text-xs text-red-700 dark:text-red-300 space-y-1"
    >
      <p class="font-bold">Gagal memuat informasi objek</p>
      <p class="leading-relaxed">{{ error }}</p>
    </div>

    <!-- ═══ 3. STATE KOSONG (EMPTY STATE) ═══════════════════════════════════ -->
    <div
      v-else-if="!features || features.length === 0"
      key="empty"
      class="p-4 rounded-2xl border border-slate-200/80 dark:border-white/10 bg-slate-50/70 dark:bg-white/[0.02] text-center space-y-1.5"
    >
      <div class="size-9 rounded-xl bg-slate-100 dark:bg-white/5 flex items-center justify-center text-slate-600 dark:text-slate-300 mx-auto">
        <UIcon name="i-ion-location-outline" class="size-4.5" />
      </div>
      <p class="text-xs font-bold text-slate-800 dark:text-slate-200">
        {{ hasPoint ? "Tidak ada data infrastruktur atau wilayah di titik ini" : "Belum ada jalan atau wilayah terpilih" }}
      </p>
      <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed max-w-[270px] mx-auto">
        Ketuk tepat pada garis jalan, jembatan, atau batas desa pada peta untuk melihat atribut lengkapnya.
      </p>
    </div>

    <!-- ═══ 4. STATE FITUR TERSEDIA (DATA LOADED) ═══════════════════════════ -->
    <div v-else key="content" class="space-y-3">
      <!-- Switcher ringkas hanya muncul jika ada lebih dari 1 fitur pada titik yang sama -->
      <div
        v-if="features.length > 1"
        class="flex items-center justify-between gap-2 px-3 py-2 rounded-2xl border border-slate-200/85 dark:border-white/10 bg-slate-50/80 dark:bg-white/[0.03]"
      >
        <div class="min-w-0 flex-1">
          <p class="text-xs font-bold text-slate-900 dark:text-slate-100 tabular-nums">
            Data {{ activeIndex + 1 }} dari {{ features.length }} di titik ini
          </p>
          <p v-if="currentFeature?.layerTitle" class="text-[11px] text-slate-600 dark:text-slate-400 truncate mt-0.5">
            {{ currentFeature.layerTitle }}
          </p>
        </div>

        <div class="flex items-center gap-1.5 shrink-0">
          <button
            type="button"
            class="size-11 rounded-xl flex items-center justify-center border border-slate-200 dark:border-white/10 bg-white dark:bg-[#070b14] text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500"
            :disabled="activeIndex === 0"
            aria-label="Data sebelumnya"
            title="Data sebelumnya"
            @click="activeIndex--"
          >
            <UIcon name="i-ion-chevron-back" class="size-4" />
          </button>
          <button
            type="button"
            class="size-11 rounded-xl flex items-center justify-center border border-slate-200 dark:border-white/10 bg-white dark:bg-[#070b14] text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/10 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500"
            :disabled="activeIndex === features.length - 1"
            aria-label="Data berikutnya"
            title="Data berikutnya"
            @click="activeIndex++"
          >
            <UIcon name="i-ion-chevron-forward" class="size-4" />
          </button>
        </div>
      </div>

      <!-- Kotak Pencarian Cepat (jika atribut lebih dari 5) -->
      <div v-if="allFormattedProperties.length > 5 || searchQuery" class="relative">
        <UIcon
          name="i-ion-search-outline"
          class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-slate-500 dark:text-slate-400 pointer-events-none"
        />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Saring atribut fitur..."
          aria-label="Saring atribut fitur"
          class="w-full min-h-[44px] h-11 pl-9 pr-11 text-base sm:text-xs rounded-full border border-slate-200/90 dark:border-white/10 bg-slate-50/70 dark:bg-[#070b14] text-slate-900 dark:text-slate-100 placeholder:text-slate-500 focus:bg-white dark:focus:bg-[#0c1424] focus:outline-hidden focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 transition-all"
        />
        <button
          v-if="searchQuery"
          type="button"
          class="absolute right-1.5 top-1/2 -translate-y-1/2 size-9 after:absolute after:-inset-1.5 after:content-[''] rounded-full flex items-center justify-center text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-200/70 dark:hover:bg-white/10 cursor-pointer focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500"
          aria-label="Hapus pencarian atribut"
          @click="searchQuery = ''"
        >
          <UIcon name="i-ion-close" class="size-4" />
        </button>
      </div>

      <!-- Daftar Atribut Feature Keseluruhan -->
      <div class="rounded-2xl bg-slate-100/85 dark:bg-white/[0.04] divide-y divide-slate-200/75 dark:divide-white/[0.06] overflow-hidden">
        <div
          v-for="prop in formattedProperties"
          :key="prop.key"
          class="flex items-center justify-between px-3.5 py-3 gap-3 hover:bg-slate-200/45 dark:hover:bg-white/[0.04] transition-colors group"
        >
          <div class="min-w-0 flex-1">
            <span class="text-[11px] font-medium text-slate-600 dark:text-slate-400 block">
              {{ prop.label }}
            </span>
            <p class="text-xs sm:text-[13px] font-semibold text-slate-900 dark:text-slate-100 break-words tabular-nums mt-0.5 select-text leading-snug">
              {{ prop.value }}
            </p>
          </div>

          <UTooltip :text="copiedKey === prop.key ? 'Tersalin' : 'Salin nilai'">
            <button
              type="button"
              class="relative after:absolute after:-inset-1.5 after:content-[''] size-9 rounded-lg flex items-center justify-center text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-100 dark:hover:bg-white/10 opacity-80 group-hover:opacity-100 focus-visible:opacity-100 transition-all cursor-pointer shrink-0 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500"
              :aria-label="`Salin ${prop.label}`"
              @click="copyValue(prop.key, String(prop.raw))"
            >
              <UIcon
                :name="copiedKey === prop.key ? 'i-ion-checkmark-circle' : 'i-ion-copy-outline'"
                class="size-4"
                :class="copiedKey === prop.key ? 'text-emerald-700 dark:text-emerald-400' : ''"
              />
            </button>
          </UTooltip>
        </div>

        <div
          v-if="formattedProperties.length === 0"
          class="p-4 text-center text-xs text-slate-600 dark:text-slate-400"
        >
          Tidak ada atribut yang cocok dengan pencarian
        </div>
      </div>
    </div>
  </div>
</template>

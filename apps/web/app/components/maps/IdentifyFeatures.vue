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
  layerId: string;
  layerTitle: string;
  geometryType?: string;
  properties: Record<string, any>;
  coordinate?: [number, number];
}

const props = withDefaults(
  defineProps<{
    features?: IdentifiedFeature[];
    loading?: boolean;
    error?: string | null;
  }>(),
  {
    features: () => [],
    loading: false,
    error: null,
  }
);

const emit = defineEmits<{
  (e: "select-feature", feature: IdentifiedFeature): void;
}>();

const selectedIndex = ref(0);
const searchQuery = ref("");
const copiedKey = ref<string | null>(null);
let copiedTimer: any = null;

const activeKugiMeta = ref<KugiMeta | null>(null);
const isLoadingKugi = ref(false);

// Reset indeks aktif jika daftar fitur berganti
watch(
  () => props.features,
  (newFeatures) => {
    if (selectedIndex.value >= newFeatures.length) {
      selectedIndex.value = 0;
    }
  },
  { deep: true }
);

const currentFeature = computed<IdentifiedFeature | null>(() => {
  if (!props.features || props.features.length === 0) return null;
  return props.features[selectedIndex.value] || props.features[0] || null;
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

// Judul utama fitur ditentukan secara dinamis tanpa mengunci nama atribut spesifik
const featureDisplayName = computed(() => {
  if (!currentFeature.value) return "Fitur Tidak Diketahui";
  const p = currentFeature.value.properties || {};

  // Cari properti string non-kosong pertama yang kuncinya mengandung 'nam' atau 'title'
  const nameEntry = Object.entries(p).find(
    ([k, v]) => typeof v === "string" && v.trim() && (k.toLowerCase().includes("nam") || k.toLowerCase().includes("title"))
  );
  if (nameEntry) return String(nameEntry[1]);

  if (currentFeature.value.id) return currentFeature.value.id;

  // Fallback ke alias KUGI jika ada
  if (activeKugiMeta.value?.aliases) {
    return activeKugiMeta.value.aliases;
  }

  // Fallback ke nilai non-kosong pertama
  const firstValid = Object.values(p).find((v) => v !== null && v !== undefined && v !== "");
  return firstValid ? String(firstValid) : "Objek Spasial";
});

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
    SRS_ID: "Sistem Referensi Spasial",
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

// Daftar properti terformat dan difilter secara dinamis berdasarkan data
const formattedProperties = computed(() => {
  if (!currentFeature.value) return [];
  const rawProps = currentFeature.value.properties || {};
  const query = searchQuery.value.trim().toLowerCase();

  // Properti internal geometri GIS yang disembunyikan
  const excludedKeys = new Set(["bbox", "geometry", "the_geom", "geom"]);

  return Object.entries(rawProps)
    .filter(([key, val]) => {
      if (excludedKeys.has(key.toLowerCase())) return false;
      if (val === null || val === undefined || val === "") return false;

      const { label } = resolveAttributeLabel(key);

      if (!query) return true;
      const matchKey = key.toLowerCase().includes(query);
      const matchLabel = label.toLowerCase().includes(query);
      const matchVal = String(val).toLowerCase().includes(query);
      return matchKey || matchLabel || matchVal;
    })
    .map(([key, val]) => {
      const { label, isKugi } = resolveAttributeLabel(key);

      // Nilai diproses secara dinamis tanpa membatasi tipe data
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

function copyValue(key: string, val: string) {
  if (navigator?.clipboard?.writeText && window.isSecureContext) {
    navigator.clipboard.writeText(val);
  }
  copiedKey.value = key;
  clearTimeout(copiedTimer);
  copiedTimer = setTimeout(() => {
    copiedKey.value = null;
  }, 1800);
}
</script>

<template>
  <div class="space-y-3">
    <Transition name="fade-feature" mode="out-in">
      <!-- ═══ 1. STATE MEMUAT (LOADING) ═══════════════════════════════════════ -->
      <div
        v-if="loading"
        key="loading"
        class="p-3.5 rounded-2xl border border-blue-200/80 dark:border-blue-900/50 bg-blue-50/50 dark:bg-blue-950/20 space-y-3 transition-colors"
      >
        <div class="flex items-center gap-3">
          <UIcon name="i-lucide-loader-2" class="size-4.5 text-blue-600 dark:text-blue-400 animate-spin shrink-0" />
          <div class="min-w-0 flex-1">
            <p class="text-xs font-bold text-blue-900 dark:text-blue-200">
              Memeriksa Data WMS...
            </p>
            <p class="text-[11px] text-blue-600/80 dark:text-blue-300/80 truncate">
              Mengambil atribut fitur dari Geoportal Bojonegoro
            </p>
          </div>
        </div>

        <!-- Skeleton placeholders for smooth micro-animation feedback -->
        <div class="space-y-2 pt-1 border-t border-blue-200/40 dark:border-blue-900/30">
          <div class="h-3 bg-blue-200/60 dark:bg-blue-800/30 rounded-md w-3/4 animate-pulse" />
          <div class="h-3 bg-blue-200/40 dark:bg-blue-800/20 rounded-md w-1/2 animate-pulse" />
        </div>
      </div>

      <!-- ═══ 2. STATE KOSONG (EMPTY STATE) ═══════════════════════════════════ -->
      <div
        v-else-if="!features || features.length === 0"
        key="empty"
        class="p-3.5 rounded-2xl border border-slate-200/80 dark:border-white/10 bg-slate-50/70 dark:bg-white/[0.02] text-center space-y-1.5"
      >
        <div class="size-9 rounded-xl bg-slate-100 dark:bg-white/5 flex items-center justify-center text-slate-400 dark:text-slate-500 mx-auto">
          <UIcon name="i-lucide-mouse-pointer-click" class="size-4.5 stroke-[1.75]" />
        </div>
        <p class="text-xs font-bold text-slate-800 dark:text-slate-200">
          Pilih Objek Peta WMS
        </p>
        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed max-w-[260px] mx-auto">
          Klik pada batas wilayah desa atau ruas jalan untuk menampilkan data atribut lengkap.
        </p>
      </div>

      <!-- ═══ 3. STATE FITUR TERSEDIA (DATA LOADED) ═══════════════════════════ -->
      <div v-else key="content" class="space-y-2.5">
        <!-- Header Kartu: Info Fitur & Kamus KUGI -->
        <div class="p-3 rounded-xl border border-blue-200/80 dark:border-blue-900/40 bg-blue-50/40 dark:bg-blue-950/20 space-y-2.5">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 flex-1 space-y-1">
              <!-- Baris Tag Meta: KUGI & FCODE -->
              <div class="flex items-center flex-wrap gap-1.5">
                <span
                  v-if="features.length > 1"
                  class="px-1.5 py-0.5 rounded-md text-[10px] font-semibold bg-slate-200/80 dark:bg-white/10 text-slate-700 dark:text-slate-300"
                >
                  {{ selectedIndex + 1 }} dari {{ features.length }}
                </span>

                <!-- Badge KUGI jika ada FCODE -->
                <span
                  v-if="activeKugiMeta"
                  class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-semibold bg-emerald-100/80 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-300/60 dark:border-emerald-800/40"
                  :title="activeKugiMeta.definition || activeKugiMeta.typeName"
                >
                  <UIcon name="i-lucide-badge-check" class="size-3 text-emerald-600 dark:text-emerald-400 shrink-0" />
                  KUGI: {{ activeKugiMeta.aliases || activeKugiMeta.typeName }}
                </span>

                <!-- Loading status kamus KUGI -->
                <span
                  v-else-if="isLoadingKugi"
                  class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-medium bg-blue-100/60 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300"
                >
                  <UIcon name="i-lucide-loader-2" class="size-3 animate-spin shrink-0" />
                  Kamus KUGI...
                </span>

                <!-- Badge Kode FCODE -->
                <span
                  v-if="currentFcode"
                  class="px-1.5 py-0.5 rounded-md text-[10px] font-mono font-medium bg-blue-100/80 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200"
                >
                  FCODE: {{ currentFcode }}
                </span>
              </div>

              <!-- Judul Objek Fitur -->
              <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate">
                {{ featureDisplayName }}
              </h4>
            </div>

            <!-- Tombol Navigasi Antar Fitur jika ada lebih dari 1 fitur -->
            <div v-if="features.length > 1" class="flex items-center gap-1 shrink-0 pt-0.5">
              <button
                type="button"
                class="size-7 rounded-lg flex items-center justify-center border border-slate-200 dark:border-white/10 bg-white dark:bg-[#070b14] text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/10 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors"
                :disabled="selectedIndex === 0"
                aria-label="Fitur Sebelumnya"
                @click="selectedIndex--"
              >
                <UIcon name="i-lucide-chevron-left" class="size-4" />
              </button>
              <button
                type="button"
                class="size-7 rounded-lg flex items-center justify-center border border-slate-200 dark:border-white/10 bg-white dark:bg-[#070b14] text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/10 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors"
                :disabled="selectedIndex === features.length - 1"
                aria-label="Fitur Berikutnya"
                @click="selectedIndex++"
              >
                <UIcon name="i-lucide-chevron-right" class="size-4" />
              </button>
            </div>
          </div>

          <!-- Keterangan Definisi Resmi KUGI jika tersedia -->
          <div
            v-if="activeKugiMeta?.definition"
            class="p-2 rounded-lg bg-white/70 dark:bg-black/25 border border-blue-100 dark:border-blue-900/30 text-[11px] text-slate-600 dark:text-slate-300 leading-relaxed"
          >
            <span class="font-semibold text-blue-700 dark:text-blue-400">Definisi KUGI: </span>
            <span>{{ activeKugiMeta.definition }}</span>
            <div v-if="activeKugiMeta.producer" class="mt-0.5 text-[10px] text-slate-400 dark:text-slate-500">
              Walidata: {{ activeKugiMeta.producer }}
            </div>
          </div>
        </div>

        <!-- Kotak Pencarian Properti Cepat (jika atribut lebih dari 5) -->
        <div v-if="formattedProperties.length > 5 || searchQuery" class="relative">
          <UIcon
            name="i-lucide-search"
            class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-slate-400 dark:text-slate-500"
          />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari atribut KUGI atau nilai..."
            class="w-full h-8 pl-8 pr-3 text-xs rounded-lg border border-slate-200/80 dark:border-white/10 bg-white dark:bg-[#070b14] text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:outline-hidden focus:ring-1 focus:ring-blue-500 transition-all"
          />
          <button
            v-if="searchQuery"
            type="button"
            class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
            aria-label="Hapus Pencarian"
            @click="searchQuery = ''"
          >
            <UIcon name="i-lucide-x" class="size-3.5" />
          </button>
        </div>

        <!-- Tabel / Daftar Pasangan Kunci-Nilai Atribut -->
        <div class="rounded-xl border border-slate-200/80 dark:border-white/10 overflow-hidden divide-y divide-slate-100 dark:divide-white/5 bg-white dark:bg-[#070b14]">
          <div
            v-for="prop in formattedProperties"
            :key="prop.key"
            class="flex items-center justify-between p-2.5 gap-2 hover:bg-slate-50/80 dark:hover:bg-white/[0.02] transition-colors group"
          >
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400 truncate">
                  {{ prop.label }}
                </span>
                <!-- Kode field teknis GIS -->
                <span class="text-[10px] font-mono text-slate-400 dark:text-slate-500">
                  [{{ prop.key }}]
                </span>
                <!-- Indikator validasi KUGI -->
                <span
                  v-if="prop.isKugi"
                  class="px-1 py-0.2 rounded text-[9px] font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/40"
                  title="Sesuai Kamus KUGI Resmi BIG"
                >
                  KUGI
                </span>
              </div>
              <p class="text-xs font-semibold text-slate-900 dark:text-slate-100 break-words font-mono mt-0.5">
                {{ prop.value }}
              </p>
            </div>

            <!-- Tombol Salin Nilai Atribut -->
            <UTooltip :text="copiedKey === prop.key ? 'Nilai disalin' : 'Salin nilai'">
              <button
                type="button"
                class="size-7 rounded-md flex items-center justify-center text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-100 dark:hover:bg-white/10 opacity-60 group-hover:opacity-100 transition-all cursor-pointer shrink-0"
                aria-label="Salin nilai atribut"
                @click="copyValue(prop.key, String(prop.raw))"
              >
                <UIcon
                  :name="copiedKey === prop.key ? 'i-lucide-check' : 'i-lucide-copy'"
                  class="size-3.5"
                  :class="copiedKey === prop.key ? 'text-green-600 dark:text-green-400' : ''"
                />
              </button>
            </UTooltip>
          </div>

          <div
            v-if="formattedProperties.length === 0"
            class="p-4 text-center text-xs text-slate-400 dark:text-slate-500"
          >
            Tidak ada atribut yang cocok dengan pencarian
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.fade-feature-enter-active,
.fade-feature-leave-active {
  transition: opacity 0.22s cubic-bezier(0.16, 1, 0.3, 1), transform 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}

.fade-feature-enter-from {
  opacity: 0;
  transform: translateY(4px);
}

.fade-feature-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>

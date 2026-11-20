<script setup lang="ts">
import { computed, ref } from "vue";
import {
  DEFAULT_DESA_POLYGON_GEOJSON,
  parseDesaGeometryInput,
  type DesaFormState,
  type DesaRightPanelMode,
  type KecamatanSeriesItem,
} from "~/types/wilayah-desa";

interface Props {
  collapsed?: boolean;
  collapsible?: boolean;
  isMobileDrawer?: boolean;
  mode: DesaRightPanelMode;
  selectedFeature?: any | null;
  loadingFeatureDetail?: boolean;
  clickedCoordinate?: [number, number] | null;
  kecamatanList: KecamatanSeriesItem[];
  selectedKecamatanId?: number | null;
  isKecamatanRestricted?: boolean;
  isDesaRestricted?: boolean;
  canCreate?: boolean;
  canEdit?: boolean;
  canDelete?: boolean;
  submitting?: boolean;
  formState: DesaFormState;
  formNameError?: string;
  formGeometryError?: string;
  resolveKecamatanName: (kecId: number | null | undefined) => string;
}

const props = withDefaults(defineProps<Props>(), {
  collapsed: false,
  collapsible: true,
  isMobileDrawer: false,
  selectedFeature: null,
  loadingFeatureDetail: false,
  clickedCoordinate: null,
  selectedKecamatanId: null,
  isKecamatanRestricted: false,
  isDesaRestricted: false,
  canCreate: false,
  canEdit: false,
  canDelete: false,
  submitting: false,
  formNameError: "",
  formGeometryError: "",
});

const emit = defineEmits<{
  (e: "update:collapsed", val: boolean): void;
  (e: "update:formNameError", val: string): void;
  (e: "update:formGeometryError", val: string): void;
  (e: "open-create"): void;
  (e: "open-edit", feat: any): void;
  (e: "cancel-form"): void;
  (e: "submit-form"): void;
  (e: "zoom-to-selected"): void;
  (e: "focus-form-geometry", geom: any): void;
  (e: "focus-kecamatan", kecId: number): void;
  (e: "request-delete", feat: any): void;
  (e: "clear-selection"): void;
  (e: "view-in-table", feat?: any): void;
}>();

const copied = ref(false);
let copyTimer: ReturnType<typeof setTimeout> | null = null;

const isFormMode = computed(() => props.mode === "create" || props.mode === "edit");

const parsedGeomSummary = computed(() => parseDesaGeometryInput(props.formState.geometry));

const hasActiveBadge = computed(() => {
  if (props.mode === "create") return "Baru";
  if (props.mode === "edit") return "Edit";
  if (props.selectedFeature) return "Terpilih";
  return null;
});

const headerTitle = computed(() => {
  if (props.mode === "create") return "Tambah Desa Baru";
  if (props.mode === "edit") return "Edit Wilayah Desa";
  return "Detail Desa";
});

const headerIcon = computed(() => {
  if (props.mode === "create") return "i-lucide-plus-circle";
  if (props.mode === "edit") return "i-lucide-pencil";
  return "i-lucide-info";
});

function formatSamplePolygon() {
  props.formState.geometry = JSON.stringify(DEFAULT_DESA_POLYGON_GEOJSON, null, 2);
  emit("update:formGeometryError", "");
}

function prettifyFormGeoJson() {
  if (!parsedGeomSummary.value.valid || !parsedGeomSummary.value.geometry) return;
  props.formState.geometry = JSON.stringify(parsedGeomSummary.value.geometry, null, 2);
  emit("update:formGeometryError", "");
}

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
  } catch {
    // Ignore clipboard fallback failure
  }
  document.body.removeChild(textArea);
}

function copyCoords() {
  if (!props.clickedCoordinate) return;
  const [lng, lat] = props.clickedCoordinate;
  const text = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
  if (navigator?.clipboard?.writeText && window.isSecureContext) {
    navigator.clipboard.writeText(text).catch(() => fallbackCopy(text));
  } else {
    fallbackCopy(text);
  }
  copied.value = true;
  if (copyTimer) clearTimeout(copyTimer);
  copyTimer = setTimeout(() => {
    copied.value = false;
  }, 1800);
}
</script>

<template>
  <component
    :is="isMobileDrawer ? 'div' : 'aside'"
    class="flex flex-col overflow-hidden w-full h-full min-h-0 flex-1 bg-white dark:bg-[#0b0f19]"
  >
    <!-- Header Strip -->
    <div
      v-if="!isMobileDrawer"
      class="flex items-center h-10 border-b border-gray-200 dark:border-gray-800 shrink-0 px-3 gap-2 justify-between select-none"
    >
      <div v-if="!collapsed" class="flex items-center gap-2 min-w-0">
        <UIcon
          :name="headerIcon"
          class="size-4 shrink-0"
          :class="
            mode === 'create'
              ? 'text-emerald-600 dark:text-emerald-400'
              : mode === 'edit'
                ? 'text-amber-600 dark:text-amber-400'
                : 'text-blue-600 dark:text-blue-400'
          "
          aria-hidden="true"
        />
        <h2 class="text-xs font-semibold text-gray-900 dark:text-white truncate">
          {{ headerTitle }}
        </h2>
        <span
          v-if="hasActiveBadge"
          :class="[
            'rounded px-1.5 py-0.5 text-[10px] font-semibold border shrink-0',
            mode === 'create'
              ? 'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-200 dark:border-emerald-800'
              : mode === 'edit'
                ? 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/60 dark:text-amber-200 dark:border-amber-800'
                : 'bg-blue-50 text-blue-800 border-blue-200 dark:bg-blue-950/60 dark:text-blue-200 dark:border-blue-800',
          ]"
        >
          {{ hasActiveBadge }}
        </span>
      </div>

      <div class="flex items-center gap-1 shrink-0">
        <UTooltip
          v-if="!collapsed && (isFormMode || selectedFeature)"
          :text="isFormMode ? 'Tutup form dan kembali' : 'Bersihkan pilihan desa'"
        >
          <button
            type="button"
            :aria-label="isFormMode ? 'Tutup form wilayah desa' : 'Bersihkan pilihan wilayah desa'"
            class="size-7 flex items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            @click="isFormMode ? emit('cancel-form') : emit('clear-selection')"
          >
            <UIcon name="i-lucide-x" class="size-4" aria-hidden="true" />
          </button>
        </UTooltip>

        <UTooltip
          v-if="collapsible"
          :text="collapsed ? 'Buka panel kanan' : 'Ciutkan panel kanan'"
        >
          <button
            type="button"
            :aria-expanded="!collapsed"
            :aria-label="collapsed ? 'Buka panel kanan' : 'Ciutkan panel kanan'"
            class="size-7 flex items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            @click="emit('update:collapsed', !collapsed)"
          >
            <UIcon
              name="i-lucide-chevron-right"
              :class="['size-4 transition-transform duration-200', collapsed ? 'rotate-180' : '']"
              aria-hidden="true"
            />
          </button>
        </UTooltip>
      </div>
    </div>

    <!-- Collapsed Desktop Rail -->
    <button
      v-if="!isMobileDrawer && collapsible && collapsed"
      type="button"
      :aria-expanded="false"
      aria-label="Buka panel detail dan form wilayah desa"
      title="Klik untuk membuka Detail & Form Desa"
      class="flex flex-col items-center pt-3 gap-3 flex-1 w-full cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900/40 transition-colors focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-blue-600"
      @click="emit('update:collapsed', false)"
    >
      <span
        :class="[
          'relative p-1.5 rounded-md',
          isFormMode || selectedFeature
            ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400'
            : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
        ]"
      >
        <UIcon :name="headerIcon" class="size-4" aria-hidden="true" />
        <span
          v-if="isFormMode || selectedFeature"
          class="absolute -top-0.5 -right-0.5 size-2 rounded-full bg-blue-600 ring-2 ring-white dark:ring-[#0b0f19]"
        />
      </span>
      <span
        class="text-xs font-medium text-gray-600 dark:text-gray-300 tracking-wide whitespace-nowrap"
        style="writing-mode: vertical-rl;"
      >
        {{ isFormMode ? (mode === 'create' ? 'Form Tambah' : 'Form Edit') : 'Detail Desa' }}
      </span>
    </button>

    <!-- Expanded Body: CREATE / EDIT FORM -->
    <template v-else-if="isFormMode">
      <form
        class="flex-1 flex flex-col min-h-0 overflow-hidden"
        @submit.prevent="emit('submit-form')"
      >
        <div class="flex-1 overflow-y-auto p-3.5 space-y-4">
          <!-- Mode Context Banner -->
          <div
            :class="[
              'flex items-center justify-between gap-2 rounded-lg border px-3 py-2 text-xs',
              mode === 'create'
                ? 'border-emerald-200 bg-emerald-50/70 text-emerald-900 dark:border-emerald-800/70 dark:bg-emerald-950/40 dark:text-emerald-200'
                : 'border-amber-200 bg-amber-50/70 text-amber-900 dark:border-amber-800/70 dark:bg-amber-950/40 dark:text-amber-200',
            ]"
          >
            <div class="min-w-0">
              <p class="font-semibold leading-tight">
                {{ mode === 'create' ? 'Input Batas Desa Baru' : 'Pembaruan Data & Poligon Desa' }}
              </p>
              <p class="text-[11px] opacity-90 truncate mt-0.5">
                {{
                  mode === 'create'
                    ? 'Pratinjau poligon tampil langsung di kanvas peta.'
                    : `Mengubah data ${formState.nama_desa || 'wilayah desa'}`
                }}
              </p>
            </div>
            <button
              v-if="parsedGeomSummary.valid && parsedGeomSummary.geometry"
              type="button"
              class="shrink-0 inline-flex items-center gap-1 rounded-md border border-current/25 bg-white/80 dark:bg-black/25 px-2 py-1 text-[11px] font-semibold hover:bg-white dark:hover:bg-black/40 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="emit('focus-form-geometry', parsedGeomSummary.geometry)"
            >
              <UIcon name="i-lucide-locate-fixed" class="size-3.5" aria-hidden="true" />
              <span>Fokus Peta</span>
            </button>
          </div>

          <!-- Section 1: Identitas Administrasi -->
          <section class="space-y-2.5" aria-labelledby="wd-sec-identitas">
            <h3
              id="wd-sec-identitas"
              class="text-[11px] font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400"
            >
              Identitas Administrasi
            </h3>

            <div>
              <label
                for="wd-nama-desa"
                class="block text-xs font-medium text-gray-800 dark:text-gray-200 mb-1"
              >
                Nama Desa <span class="text-rose-600 dark:text-rose-400">*</span>
              </label>
              <input
                id="wd-nama-desa"
                v-model="formState.nama_desa"
                type="text"
                required
                placeholder="Contoh: Sukamaju"
                :class="[
                  'w-full rounded-lg border bg-white px-3 text-xs text-gray-900 placeholder:text-gray-400 dark:bg-[#131926] dark:text-white dark:placeholder:text-gray-500 focus-visible:outline-2 focus-visible:outline-blue-600',
                  formNameError
                    ? 'border-rose-500 dark:border-rose-500'
                    : 'border-gray-300 dark:border-gray-700',
                  isMobileDrawer ? 'min-h-[44px]' : 'h-9',
                ]"
                @input="emit('update:formNameError', '')"
              />
              <p v-if="formNameError" class="mt-1 text-[11px] text-rose-600 dark:text-rose-400">
                {{ formNameError }}
              </p>
            </div>

            <div>
              <label
                for="wd-kecamatan"
                class="block text-xs font-medium text-gray-800 dark:text-gray-200 mb-1"
              >
                Kecamatan <span class="text-rose-600 dark:text-rose-400">*</span>
              </label>
              <select
                id="wd-kecamatan"
                v-model="formState.id_kecamatan"
                :disabled="isKecamatanRestricted"
                :class="[
                  'w-full rounded-lg border border-gray-300 bg-white px-2.5 text-xs text-gray-900 dark:border-gray-700 dark:bg-[#131926] dark:text-white focus-visible:outline-2 focus-visible:outline-blue-600',
                  isKecamatanRestricted ? 'opacity-75 cursor-not-allowed' : 'cursor-pointer',
                  isMobileDrawer ? 'min-h-[44px]' : 'h-9',
                ]"
              >
                <option v-if="!isKecamatanRestricted" :value="null">-- Pilih Kecamatan --</option>
                <option
                  v-for="kec in kecamatanList"
                  :key="kec.id"
                  :value="kec.id"
                >
                  {{ kec.nama_kecamatan }}
                </option>
              </select>
            </div>
          </section>

          <!-- Section 2: Kepemimpinan Desa -->
          <section class="space-y-2.5 pt-2 border-t border-gray-100 dark:border-gray-800" aria-labelledby="wd-sec-pimpinan">
            <h3
              id="wd-sec-pimpinan"
              class="text-[11px] font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400"
            >
              Kepemimpinan Desa
            </h3>

            <div>
              <label
                for="wd-pimpinan"
                class="block text-xs font-medium text-gray-800 dark:text-gray-200 mb-1"
              >
                Nama Pimpinan (Kades / Lurah)
              </label>
              <input
                id="wd-pimpinan"
                v-model="formState.nama_pimpinan"
                type="text"
                placeholder="Nama lengkap pimpinan desa"
                :class="[
                  'w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-[#131926] dark:text-white dark:placeholder:text-gray-500 focus-visible:outline-2 focus-visible:outline-blue-600',
                  isMobileDrawer ? 'min-h-[44px]' : 'h-9',
                ]"
              />
            </div>

            <div>
              <label
                for="wd-jabatan"
                class="block text-xs font-medium text-gray-800 dark:text-gray-200 mb-1"
              >
                Nama Jabatan
              </label>
              <input
                id="wd-jabatan"
                v-model="formState.nama_jabatan"
                type="text"
                placeholder="Kepala Desa"
                :class="[
                  'w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-[#131926] dark:text-white dark:placeholder:text-gray-500 focus-visible:outline-2 focus-visible:outline-blue-600',
                  isMobileDrawer ? 'min-h-[44px]' : 'h-9',
                ]"
              />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <div>
                <label
                  for="wd-nip"
                  class="block text-xs font-medium text-gray-800 dark:text-gray-200 mb-1"
                >
                  NIP
                </label>
                <input
                  id="wd-nip"
                  v-model="formState.nip"
                  type="text"
                  placeholder="Opsional"
                  :class="[
                    'w-full rounded-lg border border-gray-300 bg-white px-3 text-xs tabular-nums text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-[#131926] dark:text-white dark:placeholder:text-gray-500 focus-visible:outline-2 focus-visible:outline-blue-600',
                    isMobileDrawer ? 'min-h-[44px]' : 'h-9',
                  ]"
                />
              </div>

              <div>
                <label
                  for="wd-pangkat"
                  class="block text-xs font-medium text-gray-800 dark:text-gray-200 mb-1"
                >
                  Pangkat / Golongan
                </label>
                <input
                  id="wd-pangkat"
                  v-model="formState.pangkat_gol"
                  type="text"
                  placeholder="Opsional"
                  :class="[
                    'w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-[#131926] dark:text-white dark:placeholder:text-gray-500 focus-visible:outline-2 focus-visible:outline-blue-600',
                    isMobileDrawer ? 'min-h-[44px]' : 'h-9',
                  ]"
                />
              </div>
            </div>
          </section>

          <!-- Section 3: Geometri Batas GeoJSON -->
          <section class="space-y-2 pt-2 border-t border-gray-100 dark:border-gray-800" aria-labelledby="wd-sec-geom">
            <div class="flex items-center justify-between gap-2">
              <h3
                id="wd-sec-geom"
                class="text-[11px] font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400"
              >
                Geometri Batas (GeoJSON)
              </h3>
              <div class="flex items-center gap-1">
                <button
                  v-if="parsedGeomSummary.valid"
                  type="button"
                  class="px-2 py-0.5 rounded border border-gray-200 dark:border-gray-700 text-[11px] font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                  @click="prettifyFormGeoJson"
                >
                  Rapikan
                </button>
                <button
                  v-if="mode === 'create'"
                  type="button"
                  class="px-2 py-0.5 rounded border border-gray-200 dark:border-gray-700 text-[11px] font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                  @click="formatSamplePolygon"
                >
                  Reset Contoh
                </button>
              </div>
            </div>

            <!-- Live GeoJSON Status Pill -->
            <div
              :class="[
                'flex items-center justify-between gap-2 rounded-lg border px-2.5 py-1.5 text-[11px]',
                parsedGeomSummary.valid
                  ? 'border-emerald-200 bg-emerald-50/60 text-emerald-800 dark:border-emerald-800/70 dark:bg-emerald-950/30 dark:text-emerald-200'
                  : 'border-amber-300 bg-amber-50/70 text-amber-900 dark:border-amber-700/70 dark:bg-amber-950/30 dark:text-amber-200',
              ]"
            >
              <div class="flex items-center gap-1.5 min-w-0">
                <UIcon
                  :name="parsedGeomSummary.valid ? 'i-lucide-check-circle-2' : 'i-lucide-alert-circle'"
                  class="size-3.5 shrink-0"
                  aria-hidden="true"
                />
                <span class="truncate font-medium">
                  <template v-if="parsedGeomSummary.valid">
                    {{ parsedGeomSummary.type }} · {{ parsedGeomSummary.vertexCount.toLocaleString('id-ID') }} titik
                  </template>
                  <template v-else>
                    {{ parsedGeomSummary.error }}
                  </template>
                </span>
              </div>
              <span v-if="parsedGeomSummary.valid" class="shrink-0 text-[10px] font-semibold">
                Pratinjau Aktif
              </span>
            </div>

            <div>
              <label
                for="wd-geometry"
                class="block text-xs font-medium text-gray-800 dark:text-gray-200 mb-1"
              >
                Koordinat GeoJSON (EPSG:4326) <span class="text-rose-600 dark:text-rose-400">*</span>
              </label>
              <textarea
                id="wd-geometry"
                v-model="formState.geometry"
                rows="7"
                spellcheck="false"
                placeholder='{ "type": "Polygon", "coordinates": [[[111.85, -7.15], ...]] }'
                :class="[
                  'w-full rounded-lg border bg-white p-2.5 font-mono text-xs text-gray-900 placeholder:text-gray-400 dark:bg-[#070b14] dark:text-gray-100 dark:placeholder:text-gray-500 focus-visible:outline-2 focus-visible:outline-blue-600',
                  formGeometryError
                    ? 'border-rose-500 dark:border-rose-500'
                    : 'border-gray-300 dark:border-gray-700',
                ]"
                @input="emit('update:formGeometryError', '')"
              />
              <p v-if="formGeometryError" class="mt-1 text-[11px] text-rose-600 dark:text-rose-400">
                {{ formGeometryError }}
              </p>
              <p class="mt-1 text-[11px] text-gray-600 dark:text-gray-400">
                Mendukung format Polygon atau MultiPolygon untuk kolom PostGIS geometry.
              </p>
            </div>
          </section>
        </div>

        <!-- Sticky Form Footer -->
        <div class="border-t border-gray-200 dark:border-gray-800 shrink-0 p-3 bg-gray-50/70 dark:bg-[#0e1424] flex items-center gap-2">
          <button
            type="button"
            :disabled="submitting"
            :class="[
              'rounded-lg border border-gray-300 bg-white px-3 text-xs font-medium text-gray-700 hover:bg-gray-100 disabled:opacity-60 disabled:cursor-not-allowed dark:border-gray-700 dark:bg-[#0b0f19] dark:text-gray-200 dark:hover:bg-gray-800 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
              isMobileDrawer ? 'min-h-[44px]' : 'h-9',
            ]"
            @click="emit('cancel-form')"
          >
            Batal
          </button>

          <button
            type="submit"
            :disabled="submitting"
            :class="[
              'flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg bg-blue-600 px-3 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
              isMobileDrawer ? 'min-h-[44px]' : 'h-9',
            ]"
          >
            <UIcon
              v-if="submitting"
              name="i-lucide-loader-2"
              class="size-4 animate-spin shrink-0"
              aria-hidden="true"
            />
            <UIcon
              v-else
              name="i-lucide-check"
              class="size-4 shrink-0"
              aria-hidden="true"
            />
            <span>
              {{
                submitting
                  ? 'Menyimpan...'
                  : mode === 'edit'
                    ? 'Perbarui Desa'
                    : 'Simpan Desa'
              }}
            </span>
          </button>
        </div>
      </form>
    </template>

    <!-- Expanded Body: INSPECT MODE -->
    <div v-else class="flex-1 flex flex-col min-h-0 overflow-hidden">
      <div class="flex-1 overflow-y-auto min-h-0">
        <!-- Empty State -->
        <div
          v-if="!selectedFeature"
          class="flex flex-col items-center justify-center h-full gap-3 px-5 py-10 text-center"
        >
          <div class="size-11 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-600 dark:text-gray-300">
            <UIcon name="i-lucide-mouse-pointer-click" class="size-5" aria-hidden="true" />
          </div>
          <div class="space-y-1 max-w-[240px]">
            <p class="text-xs font-semibold text-gray-900 dark:text-white">
              Belum Ada Desa Terpilih
            </p>
            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
              Klik poligon batas desa pada peta atau pilih baris di tabel atribut untuk melihat rincian dan opsi edit.
            </p>
          </div>
          <button
            v-if="canCreate && !isDesaRestricted"
            type="button"
            :class="[
              'inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3.5 text-xs font-semibold text-white hover:bg-blue-700 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
              isMobileDrawer ? 'min-h-[44px]' : 'h-8',
            ]"
            @click="emit('open-create')"
          >
            <UIcon name="i-lucide-plus" class="size-3.5" aria-hidden="true" />
            <span>Tambah Desa Baru</span>
          </button>
        </div>

        <!-- Selected Desa Details -->
        <div v-else class="p-3.5 space-y-3.5">
          <!-- Title Header inside Inspector -->
          <div class="flex items-start justify-between gap-2 pb-2.5 border-b border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-2.5 min-w-0">
              <div class="size-8 rounded-lg bg-sky-500/15 flex items-center justify-center shrink-0">
                <UIcon name="i-lucide-map-pin" class="size-4 text-sky-600 dark:text-sky-400" aria-hidden="true" />
              </div>
              <div class="min-w-0">
                <h3 class="font-bold text-sm text-gray-900 dark:text-white truncate">
                  {{ selectedFeature.properties?.nama_desa || 'Wilayah Desa' }}
                </h3>
                <p class="text-xs text-gray-600 dark:text-gray-400 truncate">
                  Kec. {{ resolveKecamatanName(selectedFeature.properties?.id_kecamatan) }}
                </p>
              </div>
            </div>
          </div>

          <!-- Action Toolbar -->
          <div class="flex items-center gap-1.5">
            <button
              type="button"
              :class="[
                'flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 dark:bg-blue-950/60 dark:text-blue-300 dark:hover:bg-blue-900/60 px-2.5 text-xs font-semibold transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                isMobileDrawer ? 'min-h-[44px]' : 'h-8',
              ]"
              @click="emit('zoom-to-selected')"
            >
              <UIcon name="i-lucide-locate-fixed" class="size-3.5" aria-hidden="true" />
              <span>Zoom</span>
            </button>

            <button
              v-if="canEdit"
              type="button"
              :class="[
                'flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white text-gray-800 hover:bg-gray-50 dark:border-gray-700 dark:bg-[#131926] dark:text-gray-200 dark:hover:bg-gray-800 px-2.5 text-xs font-semibold transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                isMobileDrawer ? 'min-h-[44px]' : 'h-8',
              ]"
              @click="emit('open-edit', selectedFeature)"
            >
              <UIcon name="i-lucide-pencil" class="size-3.5" aria-hidden="true" />
              <span>Edit</span>
            </button>

            <button
              v-if="canDelete"
              type="button"
              aria-label="Hapus wilayah desa"
              title="Hapus Wilayah Desa"
              :class="[
                'inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 dark:border-rose-800/70 dark:bg-rose-950/50 dark:text-rose-300 dark:hover:bg-rose-900/60 px-2.5 text-xs font-semibold transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                isMobileDrawer ? 'min-h-[44px] min-w-[44px]' : 'h-8 w-8',
              ]"
              @click="emit('request-delete', selectedFeature)"
            >
              <UIcon name="i-lucide-trash-2" class="size-3.5" aria-hidden="true" />
            </button>

            <button
              type="button"
              aria-label="Lihat di tabel atribut"
              title="Lihat di Tabel"
              :class="[
                'inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-[#131926] dark:text-gray-300 dark:hover:bg-gray-800 px-2.5 text-xs font-semibold transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                isMobileDrawer ? 'min-h-[44px] min-w-[44px]' : 'h-8 w-8',
              ]"
              @click="emit('view-in-table', selectedFeature)"
            >
              <UIcon name="i-lucide-table" class="size-3.5" aria-hidden="true" />
            </button>
          </div>

          <!-- Read-only notice for restricted desa operators -->
          <div
            v-if="isDesaRestricted && !canEdit"
            class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50/70 px-3 py-2 text-[11px] text-amber-900 dark:border-amber-800/70 dark:bg-amber-950/40 dark:text-amber-200"
          >
            <UIcon name="i-lucide-info" class="size-4 shrink-0" aria-hidden="true" />
            <span>Mode baca: aksi ubah data hanya tersedia untuk wilayah desa Anda.</span>
          </div>

          <!-- Spatial Metrics Card -->
          <div class="rounded-xl bg-gray-50 dark:bg-[#070b14]/70 border border-gray-200/80 dark:border-gray-800 divide-y divide-gray-200/70 dark:divide-gray-800 text-xs">
            <div class="flex justify-between items-center px-3 py-2">
              <span class="text-gray-600 dark:text-gray-400">Luas Wilayah</span>
              <span class="font-bold text-blue-600 dark:text-blue-400 tabular-nums">
                <template v-if="loadingFeatureDetail && !selectedFeature.properties?.luas_hektar">
                  Memuat...
                </template>
                <template v-else>
                  {{ Number(selectedFeature.properties?.luas_hektar || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }} Ha
                </template>
              </span>
            </div>
            <div class="flex justify-between items-center px-3 py-2">
              <span class="text-gray-600 dark:text-gray-400">Keliling Batas</span>
              <span class="font-medium text-gray-900 dark:text-gray-200 tabular-nums">
                <template v-if="loadingFeatureDetail && !selectedFeature.properties?.keliling_meter">
                  Memuat...
                </template>
                <template v-else>
                  {{ Number(selectedFeature.properties?.keliling_meter || 0).toLocaleString('id-ID') }} m
                </template>
              </span>
            </div>
            <div class="flex justify-between items-center px-3 py-2">
              <span class="text-gray-600 dark:text-gray-400">Kecamatan Relasi</span>
              <span class="font-semibold text-gray-900 dark:text-gray-200">
                {{ resolveKecamatanName(selectedFeature.properties?.id_kecamatan) }}
              </span>
            </div>
          </div>

          <!-- Quick Action: Switch Series to Feature's Kecamatan -->
          <div
            v-if="selectedFeature.properties?.id_kecamatan && selectedKecamatanId !== selectedFeature.properties.id_kecamatan && !isKecamatanRestricted"
          >
            <button
              type="button"
              :class="[
                'w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 text-xs font-medium text-gray-800 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-200 dark:hover:bg-gray-800 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                isMobileDrawer ? 'min-h-[44px]' : 'h-8',
              ]"
              @click="emit('focus-kecamatan', Number(selectedFeature.properties.id_kecamatan))"
            >
              <UIcon name="i-lucide-layers" class="size-3.5 shrink-0" aria-hidden="true" />
              <span class="truncate">
                Fokus ke Series Kec. {{ resolveKecamatanName(selectedFeature.properties.id_kecamatan) }}
              </span>
            </button>
          </div>

          <!-- Leadership Information -->
          <div class="space-y-2 pt-1 text-xs">
            <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400">
              <UIcon name="i-lucide-user" class="size-3.5" aria-hidden="true" />
              <span class="font-semibold uppercase tracking-wider text-[11px]">
                Pimpinan Desa
              </span>
            </div>
            <div class="space-y-2 p-3 rounded-xl bg-gray-50/80 dark:bg-[#070b14]/60 border border-gray-200/80 dark:border-gray-800">
              <div>
                <span class="text-[11px] text-gray-600 dark:text-gray-400 block">Nama Pimpinan / Kades</span>
                <p class="font-semibold text-gray-900 dark:text-white mt-0.5">
                  {{ selectedFeature.properties?.nama_pimpinan || '-' }}
                </p>
              </div>
              <div>
                <span class="text-[11px] text-gray-600 dark:text-gray-400 block">Jabatan</span>
                <p class="text-gray-800 dark:text-gray-200 mt-0.5">
                  {{ selectedFeature.properties?.nama_jabatan || '-' }}
                </p>
              </div>
              <div class="grid grid-cols-2 gap-2 pt-2 border-t border-gray-200/70 dark:border-gray-800">
                <div>
                  <span class="text-[11px] text-gray-600 dark:text-gray-400 block">NIP</span>
                  <p class="text-gray-800 dark:text-gray-200 tabular-nums mt-0.5 break-all">
                    {{ selectedFeature.properties?.nip || '-' }}
                  </p>
                </div>
                <div>
                  <span class="text-[11px] text-gray-600 dark:text-gray-400 block">Pangkat / Gol</span>
                  <p class="text-gray-800 dark:text-gray-200 mt-0.5">
                    {{ selectedFeature.properties?.pangkat_gol || '-' }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Clicked Coordinate Footer -->
      <div class="border-t border-gray-200 dark:border-gray-800 shrink-0 px-3 py-2 bg-gray-50/60 dark:bg-[#0e1424]">
        <div
          v-if="clickedCoordinate"
          class="flex items-center justify-between gap-2 text-xs"
        >
          <div class="flex items-center gap-1.5 text-[11px] text-gray-700 dark:text-gray-300 min-w-0 tabular-nums">
            <span class="truncate">
              <span class="text-gray-600 dark:text-gray-400 mr-1">Lat</span>{{ clickedCoordinate[1].toFixed(6) }},
              <span class="text-gray-600 dark:text-gray-400 mr-1 ml-1.5">Lng</span>{{ clickedCoordinate[0].toFixed(6) }}
            </span>
          </div>

          <UTooltip :text="copied ? 'Tersalin!' : 'Salin koordinat'">
            <button
              type="button"
              :aria-label="copied ? 'Koordinat tersalin' : 'Salin koordinat'"
              class="h-7 px-2 inline-flex items-center justify-center rounded-md text-gray-600 hover:bg-gray-200/70 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="copyCoords"
            >
              <UIcon :name="copied ? 'i-lucide-check' : 'i-lucide-copy'" class="size-3.5" aria-hidden="true" />
            </button>
          </UTooltip>
        </div>

        <div
          v-else
          class="flex items-center gap-1.5 text-[11px] text-gray-600 dark:text-gray-400 py-0.5"
        >
          <UIcon name="i-lucide-mouse-pointer-click" class="size-3.5 shrink-0" aria-hidden="true" />
          <span>Klik peta untuk mengambil koordinat WGS84</span>
        </div>
      </div>
    </div>
  </component>
</template>

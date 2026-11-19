<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import {
  computeGeometryBbox,
  formatAngka,
  formatPanjangKm,
  getHierarkiJalanLabel,
  getStatusAnalisaMeta,
} from '~/utils/jaringan-jalan-format';
import type {
  HierarkiJalan,
  JalanKabupatenItem,
  JaringanJalanRuasFeature,
  RtrwAnalisaDetail,
} from '~/types/jaringan-jalan';

interface Props {
  activeMode: 'rtrw' | 'poros' | 'kabupaten' | null;
  rtrwDetail: RtrwAnalisaDetail | null;
  loadingRtrwDetail: boolean;
  selectedPoros: JaringanJalanRuasFeature | null;
  selectedKabupaten: JalanKabupatenItem | null;
  canManage: boolean;
  adoptingRtrw: boolean;
  savingPoros: boolean;
  deletingPoros: boolean;
  isMobileDrawer?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  isMobileDrawer: false,
});

const emit = defineEmits<{
  (
    e: 'adopt-rtrw',
    payload: {
      id: number;
      nama_ruas: string;
      hierarki_jalan: HierarkiJalan;
      tahun_survey: number;
      skip_spatial_duplicate: boolean;
    },
  ): void;
  (
    e: 'save-poros',
    payload: {
      id: string;
      kode_ruas?: string | null;
      nama_ruas: string;
      hierarki_jalan: HierarkiJalan;
      lebar_m?: number | null;
      perkerasan?: string | null;
      kondisi?: string | null;
      catatan_validasi?: string | null;
    },
  ): void;
  (e: 'request-delete-poros', feature: JaringanJalanRuasFeature): void;
  (e: 'clear-selection'): void;
  (e: 'zoom-to-bbox', bbox: [number, number, number, number] | null): void;
}>();

const hierarkiOptions: Array<{ value: HierarkiJalan; label: string }> = [
  { value: 'poros_desa', label: 'Jalan Poros Desa' },
  { value: 'lingkungan', label: 'Jalan Lingkungan' },
  { value: 'usaha_tani', label: 'Jalan Usaha Tani' },
  { value: 'kabupaten_usulan', label: 'Jalan Kabupaten Usulan' },
  { value: 'kabupaten', label: 'Jalan Kabupaten' },
];

const adoptForm = reactive({
  nama_ruas: '',
  hierarki_jalan: 'poros_desa' as HierarkiJalan,
  tahun_survey: new Date().getFullYear(),
  skip_spatial_duplicate: true,
});

const porosForm = reactive({
  kode_ruas: '',
  nama_ruas: '',
  hierarki_jalan: 'poros_desa' as HierarkiJalan,
  lebar_m: '' as string | number,
  perkerasan: '',
  kondisi: '',
  catatan_validasi: '',
});

watch(
  () => props.rtrwDetail,
  (detail) => {
    if (!detail) return;
    adoptForm.nama_ruas = detail.segment.display_name || '';
    adoptForm.hierarki_jalan = detail.segment.recommended_hierarki || 'poros_desa';
    adoptForm.tahun_survey = new Date().getFullYear();
    adoptForm.skip_spatial_duplicate = true;
  },
  { immediate: true },
);

watch(
  () => props.selectedPoros,
  (feat) => {
    if (!feat) return;
    const p = feat.properties;
    porosForm.kode_ruas = p.kode_ruas ? String(p.kode_ruas) : '';
    porosForm.nama_ruas = p.nama_ruas || '';
    porosForm.hierarki_jalan = (p.hierarki_jalan as HierarkiJalan) || 'poros_desa';
    porosForm.lebar_m = p.lebar_m ?? '';
    porosForm.perkerasan = p.perkerasan || '';
    porosForm.kondisi = p.kondisi || '';
    porosForm.catatan_validasi = '';
  },
  { immediate: true },
);

const statusMeta = computed(() =>
  getStatusAnalisaMeta(props.rtrwDetail?.segment.status_analisa),
);

function submitAdopt() {
  if (!props.rtrwDetail) return;
  emit('adopt-rtrw', {
    id: props.rtrwDetail.segment.id,
    nama_ruas: adoptForm.nama_ruas,
    hierarki_jalan: adoptForm.hierarki_jalan,
    tahun_survey: Number(adoptForm.tahun_survey) || new Date().getFullYear(),
    skip_spatial_duplicate: adoptForm.skip_spatial_duplicate,
  });
}

function submitSavePoros() {
  if (!props.selectedPoros) return;
  emit('save-poros', {
    id: props.selectedPoros.id,
    kode_ruas: porosForm.kode_ruas.trim() || null,
    nama_ruas: porosForm.nama_ruas.trim(),
    hierarki_jalan: porosForm.hierarki_jalan,
    lebar_m: porosForm.lebar_m === '' ? null : Number(porosForm.lebar_m),
    perkerasan: porosForm.perkerasan.trim() || null,
    kondisi: porosForm.kondisi.trim() || null,
    catatan_validasi: porosForm.catatan_validasi.trim() || null,
  });
}

function zoomGeom(geom: any) {
  const bbox = computeGeometryBbox(geom);
  if (bbox) emit('zoom-to-bbox', bbox);
}
</script>

<template>
  <div class="space-y-3 text-xs">
    <!-- Empty State -->
    <div
      v-if="!activeMode"
      class="rounded-lg border border-dashed border-gray-300 bg-gray-50/70 p-4 text-center space-y-2 dark:border-gray-700 dark:bg-[#0e1424]/70"
    >
      <div class="mx-auto flex size-10 items-center justify-center rounded-full bg-gray-200/70 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
        <UIcon name="i-lucide-mouse-pointer-click" class="size-5" />
      </div>
      <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
        Belum Ada Ruas Dipilih
      </h3>
      <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-400">
        Klik segmen garis pada peta vektor atau pilih baris pada tabel atribut bawah untuk menginspeksi irisan spasial, melihat padanan ruas, atau mengadopsi data.
      </p>
    </div>

    <!-- Loading Skeleton -->
    <div v-else-if="activeMode === 'rtrw' && loadingRtrwDetail" class="space-y-2.5 animate-pulse">
      <div class="h-16 rounded-lg bg-gray-200 dark:bg-gray-800" />
      <div class="h-24 rounded-lg bg-gray-200 dark:bg-gray-800" />
      <div class="h-32 rounded-lg bg-gray-200 dark:bg-gray-800" />
    </div>

    <!-- Mode 1: Inspektor Segmen Rencana Jalan RTRW -->
    <template v-else-if="activeMode === 'rtrw' && rtrwDetail">
      <!-- Identitas Utama -->
      <div class="rounded-lg border border-gray-200 bg-gray-50/80 p-3 space-y-2.5 dark:border-gray-800 dark:bg-[#0e1424]">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <span
              :class="[
                'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-semibold',
                statusMeta.badgeClass,
              ]"
            >
              <span class="size-1.5 rounded-full" :class="statusMeta.dotClass" />
              {{ statusMeta.label }}
            </span>
            <h3 class="mt-1.5 text-sm font-bold text-gray-900 dark:text-white leading-snug">
              {{ rtrwDetail.segment.display_name }}
            </h3>
            <p class="mt-0.5 text-[11px] text-gray-600 dark:text-gray-400">
              {{ rtrwDetail.segment.orde02 || 'Rencana Jaringan Jalan' }}
              <template v-if="rtrwDetail.segment.orde04">
                · {{ rtrwDetail.segment.orde04 }}
              </template>
            </p>
          </div>

          <button
            v-if="rtrwDetail.segment.bbox"
            type="button"
            aria-label="Zoom peta ke segmen ini"
            title="Zoom ke segmen"
            :class="[
              'flex items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-gray-200 dark:hover:bg-gray-800 shrink-0 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
              isMobileDrawer ? 'size-11' : 'size-8',
            ]"
            @click="emit('zoom-to-bbox', rtrwDetail.segment.bbox)"
          >
            <UIcon name="i-lucide-locate-fixed" class="size-4" />
          </button>
        </div>

        <div class="grid grid-cols-2 gap-2 pt-1 text-[11px]">
          <div class="rounded-md bg-white px-2.5 py-1.5 border border-gray-200/80 dark:bg-[#0b0f19] dark:border-gray-800">
            <span class="text-gray-600 dark:text-gray-400">Wilayah Administrasi</span>
            <p class="mt-0.5 font-semibold text-gray-900 dark:text-white truncate">
              {{ rtrwDetail.segment.nama_desa ? `Desa ${rtrwDetail.segment.nama_desa}` : 'Luar Batas Desa' }}
            </p>
            <p class="text-[11px] text-gray-600 dark:text-gray-400 truncate">
              {{ rtrwDetail.segment.nama_kecamatan ? `Kec. ${rtrwDetail.segment.nama_kecamatan}` : '-' }}
            </p>
          </div>

          <div class="rounded-md bg-white px-2.5 py-1.5 border border-gray-200/80 dark:bg-[#0b0f19] dark:border-gray-800 tabular-nums">
            <span class="text-gray-600 dark:text-gray-400">Dimensi Segmen</span>
            <p class="mt-0.5 font-semibold text-gray-900 dark:text-white">
              {{ formatAngka(rtrwDetail.segment.panjang_m, 0) }} m
            </p>
            <p class="text-[11px] text-gray-600 dark:text-gray-400">
              {{ formatPanjangKm(rtrwDetail.segment.panjang_m) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Skor Irisan Spasial -->
      <section class="rounded-lg border border-gray-200 bg-white p-3 space-y-2.5 dark:border-gray-800 dark:bg-[#0e1424]" aria-labelledby="insp-irisan-heading">
        <div class="flex items-center justify-between">
          <h3 id="insp-irisan-heading" class="text-xs font-semibold text-gray-900 dark:text-white">
            Skor Irisan Spasial
          </h3>
          <span class="text-xs font-bold tabular-nums text-gray-900 dark:text-white">
            {{ formatAngka(rtrwDetail.segment.combined_overlap_pct, 1) }}% Tercover
          </span>
        </div>

        <div class="space-y-2 text-[11px]">
          <!-- Irisan Poros -->
          <div>
            <div class="flex justify-between mb-1">
              <span class="font-medium text-emerald-800 dark:text-emerald-300">Irisan Jalan Poros Desa</span>
              <span class="tabular-nums font-semibold text-gray-900 dark:text-white">
                {{ formatAngka(rtrwDetail.segment.poros_overlap_pct, 1) }}% ({{ formatAngka(rtrwDetail.segment.poros_overlap_m, 0) }} m)
              </span>
            </div>
            <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
              <div
                class="h-full rounded-full bg-emerald-600 transition-all"
                :style="{ width: `${Math.min(100, Math.max(0, rtrwDetail.segment.poros_overlap_pct))}%` }"
              />
            </div>
          </div>

          <!-- Irisan Kabupaten -->
          <div>
            <div class="flex justify-between mb-1">
              <span class="font-medium text-sky-800 dark:text-sky-300">Irisan Jalan Kabupaten</span>
              <span class="tabular-nums font-semibold text-gray-900 dark:text-white">
                {{ formatAngka(rtrwDetail.segment.kab_overlap_pct, 1) }}% ({{ formatAngka(rtrwDetail.segment.kab_overlap_m, 0) }} m)
              </span>
            </div>
            <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
              <div
                class="h-full rounded-full bg-sky-600 transition-all"
                :style="{ width: `${Math.min(100, Math.max(0, rtrwDetail.segment.kab_overlap_pct))}%` }"
              />
            </div>
          </div>

          <!-- Belum Terdata -->
          <div class="flex items-center justify-between pt-1 border-t border-gray-200/70 dark:border-gray-800">
            <span class="font-medium text-amber-800 dark:text-amber-300">Sisa Belum Terdata (Gap)</span>
            <span class="tabular-nums font-semibold text-amber-900 dark:text-amber-200">
              {{ formatAngka(rtrwDetail.segment.uncovered_m, 0) }} m
            </span>
          </div>
        </div>
      </section>

      <!-- Padanan Jalan Poros -->
      <section class="rounded-lg border border-gray-200 bg-white p-3 space-y-2 dark:border-gray-800 dark:bg-[#0e1424]" aria-labelledby="insp-padanan-poros">
        <div class="flex items-center justify-between">
          <h3 id="insp-padanan-poros" class="text-xs font-semibold text-gray-900 dark:text-white">
            Padanan Master Jalan Poros
          </h3>
          <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] font-semibold text-gray-700 tabular-nums dark:bg-gray-800 dark:text-gray-300">
            {{ rtrwDetail.matched_poros.length }} ruas
          </span>
        </div>

        <p v-if="rtrwDetail.matched_poros.length === 0" class="text-[11px] text-gray-600 dark:text-gray-400">
          Tidak ditemukan ruas Jalan Poros Desa dalam koridor irisan 25 meter.
        </p>

        <div v-else class="space-y-1.5">
          <div
            v-for="mp in rtrwDetail.matched_poros"
            :key="mp.id"
            class="rounded-md border border-emerald-200/90 bg-emerald-50/40 p-2 dark:border-emerald-900/70 dark:bg-emerald-950/20"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="font-semibold text-gray-900 dark:text-white truncate">{{ mp.nama_ruas }}</p>
                <p class="text-[11px] text-gray-600 dark:text-gray-300 tabular-nums">
                  {{ getHierarkiJalanLabel(mp.hierarki_jalan) }} · Irisan {{ formatAngka(mp.overlap_pct, 1) }}% ({{ formatAngka(mp.overlap_m, 0) }} m)
                </p>
              </div>
              <button
                v-if="mp.geojson"
                type="button"
                aria-label="Zoom ke ruas padanan poros"
                class="flex items-center justify-center rounded border border-emerald-300 bg-white text-emerald-800 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-[#0b0f19] dark:text-emerald-300 shrink-0 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                :class="isMobileDrawer ? 'size-11' : 'size-7'"
                @click="zoomGeom(mp.geojson)"
              >
                <UIcon name="i-lucide-locate" class="size-3.5" />
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- Padanan Jalan Kabupaten -->
      <section class="rounded-lg border border-gray-200 bg-white p-3 space-y-2 dark:border-gray-800 dark:bg-[#0e1424]" aria-labelledby="insp-padanan-kab">
        <div class="flex items-center justify-between">
          <h3 id="insp-padanan-kab" class="text-xs font-semibold text-gray-900 dark:text-white">
            Padanan Jalan Kabupaten
          </h3>
          <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] font-semibold text-gray-700 tabular-nums dark:bg-gray-800 dark:text-gray-300">
            {{ rtrwDetail.matched_kabupaten.length }} ruas
          </span>
        </div>

        <p v-if="rtrwDetail.matched_kabupaten.length === 0" class="text-[11px] text-gray-600 dark:text-gray-400">
          Tidak beririsan dengan ruas Jalan Kabupaten Luarkota.
        </p>

        <div v-else class="space-y-1.5">
          <div
            v-for="mk in rtrwDetail.matched_kabupaten"
            :key="mk.id"
            class="rounded-md border border-sky-200/90 bg-sky-50/40 p-2 dark:border-sky-900/70 dark:bg-sky-950/20"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="font-semibold text-gray-900 dark:text-white truncate">
                  {{ mk.no_ruas ? `No. ${mk.no_ruas} - ` : '' }}{{ mk.nama_ruas }}
                </p>
                <p class="text-[11px] text-gray-600 dark:text-gray-300 tabular-nums">
                  {{ mk.klas_ruas || 'Kolektor/Lokal' }} · Irisan {{ formatAngka(mk.overlap_pct, 1) }}% ({{ formatAngka(mk.overlap_m, 0) }} m)
                </p>
              </div>
              <button
                v-if="mk.geojson"
                type="button"
                aria-label="Zoom ke ruas jalan kabupaten padanan"
                class="flex items-center justify-center rounded border border-sky-300 bg-white text-sky-800 hover:bg-sky-100 dark:border-sky-800 dark:bg-[#0b0f19] dark:text-sky-300 shrink-0 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
                :class="isMobileDrawer ? 'size-11' : 'size-7'"
                @click="zoomGeom(mk.geojson)"
              >
                <UIcon name="i-lucide-locate" class="size-3.5" />
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- Formulir Adopsi ke Master Jalan Poros Desa -->
      <section
        v-if="canManage && !rtrwDetail.segment.already_adopted"
        class="rounded-lg border border-emerald-300 bg-emerald-50/50 p-3 space-y-2.5 dark:border-emerald-800/80 dark:bg-emerald-950/25"
        aria-labelledby="insp-adopsi-heading"
      >
        <h3 id="insp-adopsi-heading" class="text-xs font-semibold text-emerald-950 dark:text-emerald-200">
          Adopsi Segmen ke Master Jalan Poros
        </h3>
        <div class="space-y-2">
          <div>
            <label for="adopt-nama-ruas" class="block text-[11px] font-medium text-gray-700 dark:text-gray-300 mb-1">
              Nama Ruas Master
            </label>
            <input
              id="adopt-nama-ruas"
              v-model="adoptForm.nama_ruas"
              type="text"
              class="w-full rounded-md border border-gray-300 bg-white px-2.5 text-xs text-gray-900 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-white focus-visible:outline-2 focus-visible:outline-blue-600"
              :class="isMobileDrawer ? 'min-h-[44px]' : 'h-8'"
            />
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label for="adopt-hierarki" class="block text-[11px] font-medium text-gray-700 dark:text-gray-300 mb-1">
                Hierarki Jalan
              </label>
              <select
                id="adopt-hierarki"
                v-model="adoptForm.hierarki_jalan"
                class="w-full rounded-md border border-gray-300 bg-white px-2 text-xs text-gray-900 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-white focus-visible:outline-2 focus-visible:outline-blue-600"
                :class="isMobileDrawer ? 'min-h-[44px]' : 'h-8'"
              >
                <option v-for="opt in hierarkiOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <div>
              <label for="adopt-tahun" class="block text-[11px] font-medium text-gray-700 dark:text-gray-300 mb-1">
                Tahun Survei
              </label>
              <input
                id="adopt-tahun"
                v-model.number="adoptForm.tahun_survey"
                type="number"
                min="2000"
                max="2099"
                class="w-full rounded-md border border-gray-300 bg-white px-2.5 text-xs tabular-nums text-gray-900 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-white focus-visible:outline-2 focus-visible:outline-blue-600"
                :class="isMobileDrawer ? 'min-h-[44px]' : 'h-8'"
              />
            </div>
          </div>

          <label class="flex items-center gap-2 text-[11px] text-gray-700 dark:text-gray-300 cursor-pointer" :class="isMobileDrawer ? 'min-h-[44px]' : 'py-1'">
            <input
              v-model="adoptForm.skip_spatial_duplicate"
              type="checkbox"
              class="size-4 rounded border-gray-300 accent-[#176B4D]"
            />
            <span>Lewati otomatis jika sudah terdata secara spasial</span>
          </label>

          <button
            type="button"
            :disabled="adoptingRtrw"
            :class="[
              'w-full inline-flex items-center justify-center gap-1.5 rounded-md bg-[#176B4D] px-3 text-xs font-semibold text-white hover:bg-[#12543c] disabled:opacity-60 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
              isMobileDrawer ? 'min-h-[44px]' : 'h-9',
            ]"
            @click="submitAdopt"
          >
            <UIcon v-if="adoptingRtrw" name="i-lucide-loader-2" class="size-4 animate-spin" />
            <UIcon v-else name="i-lucide-plus-circle" class="size-4" />
            <span>{{ adoptingRtrw ? 'Mengadopsi...' : 'Adopsi ke Master Jalan Poros' }}</span>
          </button>
        </div>
      </section>

      <div
        v-else-if="rtrwDetail.segment.already_adopted"
        class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200"
      >
        Segmen ini sudah tercatat di Master Jalan Poros Desa.
      </div>
    </template>

    <!-- Mode 2: Inspektor Master Jalan Poros Desa -->
    <template v-else-if="activeMode === 'poros' && selectedPoros">
      <div class="rounded-lg border border-gray-200 bg-gray-50/80 p-3 space-y-2 dark:border-gray-800 dark:bg-[#0e1424]">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <span class="inline-flex items-center rounded-full border border-emerald-300 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-200">
              {{ getHierarkiJalanLabel(selectedPoros.properties.hierarki_jalan) }}
            </span>
            <h3 class="mt-1.5 text-sm font-bold text-gray-900 dark:text-white leading-snug">
              {{ selectedPoros.properties.nama_ruas }}
            </h3>
            <p class="text-[11px] text-gray-600 dark:text-gray-400">
              {{ selectedPoros.properties.nama_desa ? `Desa ${selectedPoros.properties.nama_desa}` : '-' }}
              · Kec. {{ selectedPoros.properties.nama_kecamatan || '-' }}
            </p>
          </div>
          <button
            v-if="selectedPoros.geometry"
            type="button"
            aria-label="Zoom ke ruas jalan poros"
            :class="[
              'flex items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-gray-200 shrink-0 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
              isMobileDrawer ? 'size-11' : 'size-8',
            ]"
            @click="zoomGeom(selectedPoros.geometry)"
          >
            <UIcon name="i-lucide-locate-fixed" class="size-4" />
          </button>
        </div>

        <div class="grid grid-cols-2 gap-2 text-[11px] tabular-nums">
          <div class="rounded bg-white px-2.5 py-1.5 border border-gray-200/80 dark:bg-[#0b0f19] dark:border-gray-800">
            <span class="text-gray-600 dark:text-gray-400">Panjang</span>
            <p class="font-semibold text-gray-900 dark:text-white">
              {{ formatAngka(selectedPoros.properties.panjang_m, 0) }} m
            </p>
          </div>
          <div class="rounded bg-white px-2.5 py-1.5 border border-gray-200/80 dark:bg-[#0b0f19] dark:border-gray-800">
            <span class="text-gray-600 dark:text-gray-400">Sumber Data</span>
            <p class="font-semibold text-gray-900 dark:text-white truncate">
              {{ selectedPoros.properties.sumber_data || 'Eksisting' }}
            </p>
          </div>
        </div>
      </div>

      <!-- Edit Form -->
      <section v-if="canManage" class="rounded-lg border border-gray-200 bg-white p-3 space-y-2.5 dark:border-gray-800 dark:bg-[#0e1424]" aria-labelledby="insp-edit-poros">
        <h3 id="insp-edit-poros" class="text-xs font-semibold text-gray-900 dark:text-white">
          Pembaruan Atribut Master Ruas
        </h3>
        <div class="space-y-2">
          <div>
            <label for="poros-nama" class="block text-[11px] font-medium text-gray-700 dark:text-gray-300 mb-1">
              Nama Ruas
            </label>
            <input
              id="poros-nama"
              v-model="porosForm.nama_ruas"
              type="text"
              class="w-full rounded-md border border-gray-300 bg-white px-2.5 text-xs text-gray-900 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-white focus-visible:outline-2 focus-visible:outline-blue-600"
              :class="isMobileDrawer ? 'min-h-[44px]' : 'h-8'"
            />
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label for="poros-kode" class="block text-[11px] font-medium text-gray-700 dark:text-gray-300 mb-1">
                Kode Ruas
              </label>
              <input
                id="poros-kode"
                v-model="porosForm.kode_ruas"
                type="text"
                class="w-full rounded-md border border-gray-300 bg-white px-2.5 text-xs tabular-nums text-gray-900 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-white focus-visible:outline-2 focus-visible:outline-blue-600"
                :class="isMobileDrawer ? 'min-h-[44px]' : 'h-8'"
              />
            </div>
            <div>
              <label for="poros-hierarki" class="block text-[11px] font-medium text-gray-700 dark:text-gray-300 mb-1">
                Hierarki
              </label>
              <select
                id="poros-hierarki"
                v-model="porosForm.hierarki_jalan"
                class="w-full rounded-md border border-gray-300 bg-white px-2 text-xs text-gray-900 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-white focus-visible:outline-2 focus-visible:outline-blue-600"
                :class="isMobileDrawer ? 'min-h-[44px]' : 'h-8'"
              >
                <option v-for="opt in hierarkiOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-2">
            <div>
              <label for="poros-lebar" class="block text-[11px] font-medium text-gray-700 dark:text-gray-300 mb-1">
                Lebar (m)
              </label>
              <input
                id="poros-lebar"
                v-model="porosForm.lebar_m"
                type="number"
                step="0.1"
                class="w-full rounded-md border border-gray-300 bg-white px-2 text-xs tabular-nums text-gray-900 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-white focus-visible:outline-2 focus-visible:outline-blue-600"
                :class="isMobileDrawer ? 'min-h-[44px]' : 'h-8'"
              />
            </div>
            <div>
              <label for="poros-perkerasan" class="block text-[11px] font-medium text-gray-700 dark:text-gray-300 mb-1">
                Perkerasan
              </label>
              <input
                id="poros-perkerasan"
                v-model="porosForm.perkerasan"
                type="text"
                placeholder="Aspal/Beton"
                class="w-full rounded-md border border-gray-300 bg-white px-2 text-xs text-gray-900 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-white focus-visible:outline-2 focus-visible:outline-blue-600"
                :class="isMobileDrawer ? 'min-h-[44px]' : 'h-8'"
              />
            </div>
            <div>
              <label for="poros-kondisi" class="block text-[11px] font-medium text-gray-700 dark:text-gray-300 mb-1">
                Kondisi
              </label>
              <input
                id="poros-kondisi"
                v-model="porosForm.kondisi"
                type="text"
                placeholder="Baik/Sedang"
                class="w-full rounded-md border border-gray-300 bg-white px-2 text-xs text-gray-900 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-white focus-visible:outline-2 focus-visible:outline-blue-600"
                :class="isMobileDrawer ? 'min-h-[44px]' : 'h-8'"
              />
            </div>
          </div>

          <div class="flex items-center gap-2 pt-1">
            <button
              type="button"
              :disabled="savingPoros"
              :class="[
                'flex-1 inline-flex items-center justify-center gap-1.5 rounded-md bg-blue-700 px-3 text-xs font-semibold text-white hover:bg-blue-800 disabled:opacity-60 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                isMobileDrawer ? 'min-h-[44px]' : 'h-9',
              ]"
              @click="submitSavePoros"
            >
              <UIcon v-if="savingPoros" name="i-lucide-loader-2" class="size-4 animate-spin" />
              <UIcon v-else name="i-lucide-save" class="size-4" />
              <span>{{ savingPoros ? 'Menyimpan...' : 'Simpan Perubahan' }}</span>
            </button>

            <button
              type="button"
              :disabled="deletingPoros"
              aria-label="Hapus ruas jalan poros desa"
              :class="[
                'inline-flex items-center justify-center rounded-md border border-rose-300 bg-rose-50 px-3 text-xs font-semibold text-rose-800 hover:bg-rose-100 disabled:opacity-60 dark:border-rose-800 dark:bg-rose-950/60 dark:text-rose-200 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
                isMobileDrawer ? 'min-h-[44px]' : 'h-9',
              ]"
              @click="emit('request-delete-poros', selectedPoros)"
            >
              <UIcon name="i-lucide-trash-2" class="size-4" />
            </button>
          </div>
        </div>
      </section>
    </template>

    <!-- Mode 3: Inspektor Jalan Kabupaten -->
    <template v-else-if="activeMode === 'kabupaten' && selectedKabupaten">
      <div class="rounded-lg border border-gray-200 bg-gray-50/80 p-3 space-y-2 dark:border-gray-800 dark:bg-[#0e1424]">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <span class="inline-flex items-center rounded-full border border-sky-300 bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-sky-900 dark:border-sky-700 dark:bg-sky-950/80 dark:text-sky-200">
              {{ selectedKabupaten.klas_ruas || 'Jalan Kabupaten' }}
            </span>
            <h3 class="mt-1.5 text-sm font-bold text-gray-900 dark:text-white leading-snug">
              {{ selectedKabupaten.nama_ruas }}
            </h3>
            <p class="text-[11px] text-gray-600 dark:text-gray-400">
              No. Ruas {{ selectedKabupaten.no_ruas || '-' }} · Kec. {{ selectedKabupaten.nama_kecamatan || '-' }}
            </p>
          </div>
          <button
            v-if="selectedKabupaten.bbox"
            type="button"
            aria-label="Zoom ke ruas jalan kabupaten"
            :class="[
              'flex items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-[#0b0f19] dark:text-gray-200 shrink-0 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600',
              isMobileDrawer ? 'size-11' : 'size-8',
            ]"
            @click="emit('zoom-to-bbox', selectedKabupaten.bbox)"
          >
            <UIcon name="i-lucide-locate-fixed" class="size-4" />
          </button>
        </div>

        <dl class="grid grid-cols-2 gap-2 pt-1 text-[11px]">
          <div class="rounded bg-white px-2.5 py-1.5 border border-gray-200/80 dark:bg-[#0b0f19] dark:border-gray-800">
            <dt class="text-gray-600 dark:text-gray-400">Pangkal Ruas</dt>
            <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">
              {{ selectedKabupaten.pangkal || '-' }}
            </dd>
          </div>
          <div class="rounded bg-white px-2.5 py-1.5 border border-gray-200/80 dark:bg-[#0b0f19] dark:border-gray-800">
            <dt class="text-gray-600 dark:text-gray-400">Ujung Ruas</dt>
            <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">
              {{ selectedKabupaten.ujung || '-' }}
            </dd>
          </div>
          <div class="rounded bg-white px-2.5 py-1.5 border border-gray-200/80 dark:bg-[#0b0f19] dark:border-gray-800 tabular-nums">
            <dt class="text-gray-600 dark:text-gray-400">Panjang Tercatat</dt>
            <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">
              {{ formatAngka(selectedKabupaten.panjang_m, 0) }} m
            </dd>
          </div>
          <div class="rounded bg-white px-2.5 py-1.5 border border-gray-200/80 dark:bg-[#0b0f19] dark:border-gray-800 tabular-nums">
            <dt class="text-gray-600 dark:text-gray-400">Irisan Segmen RTRW</dt>
            <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">
              {{ formatAngka(selectedKabupaten.rtrw_match_count, 0) }} segmen
            </dd>
          </div>
        </dl>
      </div>
    </template>
  </div>
</template>

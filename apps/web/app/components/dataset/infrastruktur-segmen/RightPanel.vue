<script setup lang="ts">
import type {
  InfrastrukturSegmen,
  InfrastrukturTipe,
  PlottingAnggaran,
} from '~/types/infrastruktur';

interface Props {
  collapsed?: boolean;
  width?: number;
  selectedSegmen?: InfrastrukturSegmen | null;
  clickedCoordinate?: [number, number] | null;
  loading?: boolean;
  isMobileDrawer?: boolean;
  collapsible?: boolean;
  tipeList?: InfrastrukturTipe[];
  plottingList?: PlottingAnggaran[];
  canEdit?: boolean;
  canDelete?: boolean;
  canSubmit?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  collapsed: false,
  width: 300,
  selectedSegmen: null,
  clickedCoordinate: null,
  loading: false,
  isMobileDrawer: false,
  collapsible: true,
  tipeList: () => [],
  plottingList: () => [],
  canEdit: true,
  canDelete: true,
  canSubmit: true,
});

const emit = defineEmits<{
  (e: 'update:collapsed', val: boolean): void;
  (e: 'zoomToFeature', segmen: InfrastrukturSegmen): void;
  (e: 'editSegmen', segmen: InfrastrukturSegmen): void;
  (e: 'deleteSegmen', segmen: InfrastrukturSegmen): void;
  (e: 'submitVerifikasi', segmen: InfrastrukturSegmen): void;
  (e: 'viewInTable', segmen: InfrastrukturSegmen): void;
  (e: 'saveSegmen', payload: Partial<InfrastrukturSegmen>): void;
}>();

const activeTab = ref<'inspector' | 'edit'>('inspector');

// Local edit form state synced with selectedSegmen
const editForm = reactive<Partial<InfrastrukturSegmen>>({
  namobj: '',
  tipe_kode: '',
  panjang: null,
  lebar: null,
  kondisi: 'Baik',
  status_kondisi: 'Eksisting',
  tahun_pembangunan: new Date().getFullYear(),
  sumber_dana: '',
  status_aset: '',
  sumber_data: '',
  keterangan: '',
  plotting_id: null,
});

const copied = ref(false);
let copyTimer: any = null;

function fallbackCopy(text: string) {
  const textArea = document.createElement('textarea');
  textArea.value = text;
  textArea.style.position = 'fixed';
  textArea.style.opacity = '0';
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();
  try {
    document.execCommand('copy');
  } catch (err) {
    console.error('Gagal menyalin:', err);
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
  clearTimeout(copyTimer);
  copyTimer = setTimeout(() => {
    copied.value = false;
  }, 1800);
}

watch(
  () => props.selectedSegmen,
  (seg) => {
    activeTab.value = 'inspector';
    if (seg) {
      editForm.namobj = seg.namobj || '';
      editForm.tipe_kode = seg.tipe_kode || '';
      editForm.panjang = seg.panjang ?? seg.panjang_meter_gis ?? null;
      editForm.lebar = seg.lebar ?? null;
      editForm.kondisi = seg.kondisi || 'Baik';
      editForm.status_kondisi = seg.status_kondisi || 'Eksisting';
      editForm.tahun_pembangunan = seg.tahun_pembangunan || new Date().getFullYear();
      editForm.sumber_dana = seg.sumber_dana || '';
      editForm.status_aset = seg.status_aset || '';
      editForm.sumber_data = seg.sumber_data || '';
      editForm.keterangan = seg.keterangan || '';
      editForm.plotting_id = seg.plotting_id || null;
    }
  },
  { immediate: true }
);

const tipeItems = computed(() =>
  props.tipeList.map((t) => ({
    label: t.nama,
    value: t.kode,
  }))
);

const plottingItems = computed(() => [
  { label: 'Tanpa Kaitan Plotting (Mandiri)', value: null },
  ...props.plottingList.map((p) => ({
    label: `${p.nama_kegiatan} (${formatRupiah(p.target_pagu_anggaran)})`,
    value: p.id,
  })),
]);

const kondisiItems = [
  { label: 'Baik', value: 'Baik' },
  { label: 'Sedang', value: 'Sedang' },
  { label: 'Rusak Ringan', value: 'Rusak Ringan' },
  { label: 'Rusak Berat', value: 'Rusak Berat' },
];

function formatRupiah(val?: number | null) {
  if (val === null || val === undefined) return 'Rp 0';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val);
}

const tipeLabel = computed(() => {
  if (!props.selectedSegmen) return '';
  if (props.selectedSegmen.tipe?.nama) return props.selectedSegmen.tipe.nama;
  const match = props.tipeList.find((t) => t.kode === props.selectedSegmen?.tipe_kode);
  return match?.nama || props.selectedSegmen.tipe_kode || 'Segmen';
});

function getStatusVerifikasiBadge(status?: string | null) {
  const s = String(status || '').toLowerCase();
  switch (s) {
    case 'verified_bappeda':
      return { label: 'Disahkan Bappeda', color: 'success' as const };
    case 'verified_kecamatan':
      return { label: 'Disetujui Kecamatan', color: 'info' as const };
    case 'submitted_desa':
      return { label: 'Diajukan ke Kecamatan', color: 'warning' as const };
    case 'rejected_kecamatan':
    case 'rejected_bappeda':
      return { label: 'Perlu Revisi', color: 'error' as const };
    default:
      return { label: 'Draft', color: 'neutral' as const };
  }
}

function getKondisiBadge(kondisi?: string | null) {
  switch (kondisi) {
    case 'Baik':
      return { label: 'Baik', color: 'success' as const };
    case 'Sedang':
      return { label: 'Sedang', color: 'warning' as const };
    case 'Rusak Ringan':
      return { label: 'Rusak Ringan', color: 'warning' as const };
    case 'Rusak Berat':
      return { label: 'Rusak Berat', color: 'error' as const };
    default:
      return { label: kondisi || 'Belum Diisi', color: 'neutral' as const };
  }
}

function handleSaveEdit() {
  emit('saveSegmen', { ...editForm });
}
</script>

<template>
  <component
    :is="isMobileDrawer ? 'div' : 'aside'"
    :class="[
      'flex flex-col overflow-hidden w-full h-full min-h-0 flex-1 bg-white dark:bg-[#0b0f19]',
      isMobileDrawer ? '' : 'select-none'
    ]"
  >
    <!-- Header: Title & Collapse Button -->
    <div class="flex items-center h-10 border-b border-gray-200 dark:border-gray-800 shrink-0 px-3 gap-2 justify-between select-none">
      <Transition name="panel-subtle-fade">
        <div v-if="isMobileDrawer || !collapsed" class="flex items-center gap-2 overflow-hidden">
          <UIcon name="i-lucide-info" class="size-4 text-blue-600 dark:text-blue-400 shrink-0" />
          <span class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">
            {{ activeTab === 'inspector' ? 'Detail Data' : 'Edit Formulir' }}
          </span>
          <UIcon
            v-if="loading"
            name="i-lucide-loader-2"
            class="size-3 text-blue-500 animate-spin shrink-0"
            title="Memperbarui data detail..."
          />
          <UBadge
            v-if="selectedSegmen"
            :label="tipeLabel"
            size="xs"
            color="primary"
            variant="subtle"
            class="text-[9px] px-1 py-0 h-4 leading-none shrink-0"
          />
        </div>
      </Transition>

      <!-- Desktop Collapse Toggle Button -->
      <UTooltip v-if="!isMobileDrawer && collapsible" :text="collapsed ? 'Buka panel detail' : 'Tutup panel detail'">
        <UButton
          icon="i-lucide-chevron-right"
          size="xs"
          color="neutral"
          variant="ghost"
          :class="[
            'shrink-0 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white cursor-pointer transition-transform duration-200',
            collapsed ? 'rotate-180' : 'rotate-0'
          ]"
          @click="emit('update:collapsed', !collapsed)"
        />
      </UTooltip>
    </div>

    <Transition name="panel-fade" mode="out-in">
      <!-- Desktop Collapsed Rail -->
      <div
        v-if="!isMobileDrawer && collapsible && collapsed"
        key="rail"
        class="flex flex-col items-center pt-3 gap-3 flex-1 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900/40 transition-colors"
        title="Klik untuk membuka detail segmen"
        @click="emit('update:collapsed', false)"
      >
        <UTooltip text="Detail Segmen" :content="{ side: 'left' }">
          <div class="relative p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800">
            <UIcon
              name="i-lucide-info"
              :class="[
                'size-4 transition-colors',
                selectedSegmen ? 'text-blue-500' : 'text-gray-400 dark:text-gray-500'
              ]"
            />
            <span
              v-if="selectedSegmen"
              class="absolute top-1 right-1 size-1.5 rounded-full bg-blue-500 ring-1 ring-white dark:ring-[#0b0f19]"
            />
          </div>
        </UTooltip>
      </div>

      <!-- Content: Body Area -->
      <div
        v-else
        key="body"
        class="flex-1 flex flex-col min-h-0 overflow-hidden"
      >
        <!-- Empty State: Belum ada segmen terpilih -->
        <div
          v-if="!selectedSegmen"
          class="flex flex-col items-center justify-center h-full gap-2.5 px-4 py-8 text-center text-gray-400"
        >
          <div class="size-10 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-500">
            <UIcon name="i-lucide-mouse-pointer-click" class="size-5" />
          </div>
          <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Belum Ada Segmen Terpilih</p>
          <p class="text-[11px] text-gray-400 max-w-[220px] leading-relaxed">
            Pilih garis segmen fisik pada peta canvas atau baris di tabel atribut untuk menginspeksi detail properties.
          </p>
        </div>

        <!-- Selected Feature Detail Properties -->
        <div v-else class="flex-1 flex flex-col min-h-0">
          <!-- Top Action Toolbar -->
          <div class="p-3 pb-2 border-b border-gray-100 dark:border-gray-800/80 bg-gray-50/50 dark:bg-gray-900/30 shrink-0">
            <div class="flex items-center gap-1.5">
              <UButton
                icon="i-lucide-map-pin"
                label="Zoom"
                size="xs"
                color="primary"
                variant="subtle"
                class="flex-1 justify-center cursor-pointer"
                @click="emit('zoomToFeature', selectedSegmen)"
              />
              <UButton
                v-if="canEdit"
                icon="i-lucide-pencil"
                label="Edit"
                size="xs"
                color="neutral"
                variant="subtle"
                class="flex-1 justify-center cursor-pointer"
                @click="activeTab = activeTab === 'edit' ? 'inspector' : 'edit'"
              />
              <UButton
                v-if="canSubmit && (String(selectedSegmen.status_verifikasi || '').toLowerCase() === 'draft' || String(selectedSegmen.status_verifikasi || '').toLowerCase() === 'rejected_kecamatan')"
                icon="i-lucide-send"
                size="xs"
                color="primary"
                variant="ghost"
                title="Ajukan Verifikasi"
                class="cursor-pointer text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/40"
                @click="emit('submitVerifikasi', selectedSegmen)"
              />
              <UButton
                v-if="canDelete"
                icon="i-lucide-trash-2"
                size="xs"
                color="error"
                variant="subtle"
                title="Hapus Segmen"
                class="cursor-pointer"
                @click="emit('deleteSegmen', selectedSegmen)"
              />
              <UButton
                icon="i-lucide-table"
                size="xs"
                color="neutral"
                variant="ghost"
                title="Lihat di tabel"
                class="cursor-pointer"
                @click="emit('viewInTable', selectedSegmen)"
              />
            </div>

            <!-- Segmented Subtabs -->
            <div class="grid grid-cols-2 gap-1 p-0.5 bg-gray-200/60 dark:bg-gray-800 rounded-lg mt-2">
              <button
                type="button"
                class="py-1 px-2 rounded-md text-xs font-medium transition-all text-center cursor-pointer"
                :class="activeTab === 'inspector'
                  ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                @click="activeTab = 'inspector'"
              >
                Informasi Teknis
              </button>
              <button
                v-if="canEdit"
                type="button"
                class="py-1 px-2 rounded-md text-xs font-medium transition-all text-center cursor-pointer"
                :class="activeTab === 'edit'
                  ? 'bg-white dark:bg-[#0b0f19] text-blue-600 dark:text-blue-400 shadow-xs font-semibold'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                @click="activeTab = 'edit'"
              >
                Edit Formulir
              </button>
            </div>
          </div>

          <!-- Scrollable Body -->
          <div class="flex-1 overflow-y-auto p-3 space-y-3 text-xs">
            <!-- TAB 1: INFORMASI TEKNIS -->
            <template v-if="activeTab === 'inspector'">
              <!-- Info banner if segmen cannot be edited by user (mode baca) -->
              <div
                v-if="!canEdit && !canDelete && !canSubmit"
                class="text-[11px] text-gray-500 dark:text-gray-400 italic p-2 bg-gray-50 dark:bg-gray-900/40 rounded-lg border border-gray-100 dark:border-gray-800/80 flex items-center gap-1.5"
              >
                <UIcon name="i-lucide-info" class="size-3.5 text-amber-500 shrink-0" />
                <span>Mode baca: Segmen fisik ini di luar wewenang wilayah Anda.</span>
              </div>

              <!-- Status & Title Card -->
              <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 space-y-2">
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0 flex-1">
                    <h3 class="font-bold text-sm text-gray-900 dark:text-white leading-tight">
                      {{ selectedSegmen.namobj || 'Segmen Tanpa Nama' }}
                    </h3>
                    <p class="text-[11px] text-gray-500 font-mono mt-0.5">
                      ID: {{ String(selectedSegmen.id || '').slice(0, 8) }}...
                    </p>
                  </div>
                  <UBadge
                    :label="getStatusVerifikasiBadge(selectedSegmen.status_verifikasi).label"
                    :color="getStatusVerifikasiBadge(selectedSegmen.status_verifikasi).color"
                    variant="subtle"
                    size="xs"
                  />
                </div>

                <!-- Badges -->
                <div class="flex flex-wrap items-center gap-1.5 pt-1">
                  <UBadge
                    :label="getKondisiBadge(selectedSegmen.kondisi).label"
                    :color="getKondisiBadge(selectedSegmen.kondisi).color"
                    variant="subtle"
                    size="xs"
                  />
                  <UBadge
                    v-if="selectedSegmen.status_aset"
                    :label="selectedSegmen.status_aset"
                    color="neutral"
                    variant="subtle"
                    size="xs"
                  />
                  <UBadge
                    v-if="selectedSegmen.tahun_pembangunan"
                    :label="`Tahun ${selectedSegmen.tahun_pembangunan}`"
                    color="neutral"
                    variant="subtle"
                    size="xs"
                  />
                </div>
              </div>

              <!-- Properties Key-Value Table -->
              <div class="rounded-lg bg-gray-50 dark:bg-gray-900/40 overflow-hidden divide-y divide-gray-100 dark:divide-gray-800/60 border border-gray-100 dark:border-gray-800/80">
                <div
                  v-for="[key, val] in [
                    ['Nama Objek', selectedSegmen.namobj],
                    ['Tipe', tipeLabel],
                    ['Desa', selectedSegmen.desa],
                    ['Kecamatan', selectedSegmen.kecamatan],
                    ['Panjang (GIS)', `${Number(selectedSegmen.panjang_meter_gis ?? selectedSegmen.panjang ?? 0).toLocaleString('id-ID')} m`],
                    ['Lebar Fisik', selectedSegmen.lebar ? `${selectedSegmen.lebar} m` : '-'],
                    ['Kondisi', selectedSegmen.kondisi],
                    ['Status Kondisi', selectedSegmen.status_kondisi],
                    ['Sumber Dana', selectedSegmen.sumber_dana],
                    ['Status Aset', selectedSegmen.status_aset],
                    ['Tahun Bangun', selectedSegmen.tahun_pembangunan],
                    ['Sumber Data', selectedSegmen.sumber_data],
                  ]"
                  :key="key"
                  class="flex items-start justify-between px-3 py-1.5 text-xs"
                >
                  <span class="text-gray-500 dark:text-gray-400 font-mono w-[38%] shrink-0">{{ key }}</span>
                  <span class="text-gray-800 dark:text-gray-200 font-mono text-right break-all">{{ val !== null && val !== undefined && val !== '' ? val : '-' }}</span>
                </div>
              </div>

              <!-- Plotting Anggaran Card -->
              <div class="space-y-1.5">
                <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">
                  Plotting Anggaran Terkait
                </span>
                <div v-if="selectedSegmen.plotting" class="p-2.5 rounded-lg bg-blue-50/30 dark:bg-blue-950/20 border border-blue-500/20 space-y-1">
                  <p class="font-semibold text-blue-700 dark:text-blue-300">
                    {{ selectedSegmen.plotting.nama_kegiatan }}
                  </p>
                  <div class="flex justify-between text-[11px]">
                    <span class="text-gray-500">Target Pagu:</span>
                    <span class="font-mono font-medium text-gray-900 dark:text-white">
                      {{ formatRupiah(selectedSegmen.plotting.target_pagu_anggaran) }}
                    </span>
                  </div>
                </div>
                <div v-else class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/40 border border-dashed border-gray-200 dark:border-gray-800 text-gray-400 text-center">
                  Belum terikat ke alokasi plotting anggaran.
                </div>
              </div>

              <!-- Foto Dokumentasi Lapangan -->
              <div class="space-y-1.5">
                <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">
                  Foto Dokumentasi Lapangan
                </span>
                <div v-if="selectedSegmen.foto_url" class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-800">
                  <img
                    :src="selectedSegmen.foto_url"
                    alt="Foto Survei Segmen"
                    class="w-full h-36 object-cover bg-gray-100"
                  />
                </div>
                <div v-else class="p-3 rounded-lg bg-gray-50 dark:bg-gray-900/40 border border-dashed border-gray-200 dark:border-gray-800 text-center text-gray-400">
                  <UIcon name="i-lucide-camera" class="size-5 mx-auto mb-1 text-gray-400" />
                  <p class="text-[11px]">Belum ada foto dokumentasi terlampir.</p>
                </div>
              </div>

              <!-- Catatan Verifikasi -->
              <div
                v-if="selectedSegmen.catatan_kecamatan || selectedSegmen.catatan_bappeda"
                class="p-2.5 rounded-lg bg-amber-50/40 dark:bg-amber-950/20 border border-amber-500/30 space-y-1"
              >
                <div class="flex items-center gap-1 font-semibold text-amber-700 dark:text-amber-400 text-[11px]">
                  <UIcon name="i-lucide-alert-circle" class="size-3.5" />
                  <span>Catatan Verifikator</span>
                </div>
                <p v-if="selectedSegmen.catatan_kecamatan" class="text-[11px] text-gray-700 dark:text-gray-300">
                  <span class="font-medium">Kecamatan:</span> {{ selectedSegmen.catatan_kecamatan }}
                </p>
                <p v-if="selectedSegmen.catatan_bappeda" class="text-[11px] text-gray-700 dark:text-gray-300">
                  <span class="font-medium">Bappeda:</span> {{ selectedSegmen.catatan_bappeda }}
                </p>
              </div>
            </template>

            <!-- TAB 2: EDIT FORMULIR -->
            <template v-else-if="activeTab === 'edit'">
              <div class="space-y-3">
                <UFormField label="Nama Objek / Ruas" required size="sm">
                  <UInput
                    v-model="editForm.namobj"
                    placeholder="Contoh: Jl. Poros Desa Mulyoagung"
                    size="sm"
                    class="w-full"
                  />
                </UFormField>

                <UFormField label="Tipe Infrastruktur" required size="sm">
                  <USelectMenu
                    v-model="editForm.tipe_kode"
                    :items="tipeItems"
                    value-key="value"
                    label-key="label"
                    placeholder="Pilih tipe..."
                    size="sm"
                    class="w-full"
                  />
                </UFormField>

                <div class="grid grid-cols-2 gap-2">
                  <UFormField label="Panjang (m)" size="sm">
                    <UInputNumber
                      v-model="editForm.panjang"
                      :step="1"
                      placeholder="Panjang meter"
                      size="sm"
                      class="w-full"
                    />
                  </UFormField>

                  <UFormField label="Lebar (m)" size="sm">
                    <UInputNumber
                      v-model="editForm.lebar"
                      :step="0.1"
                      placeholder="Lebar meter"
                      size="sm"
                      class="w-full"
                    />
                  </UFormField>
                </div>

                <div class="grid grid-cols-2 gap-2">
                  <UFormField label="Kondisi Fisik" size="sm">
                    <USelectMenu
                      v-model="editForm.kondisi"
                      :items="kondisiItems"
                      value-key="value"
                      label-key="label"
                      size="sm"
                      class="w-full"
                    />
                  </UFormField>

                  <UFormField label="Tahun Bangun" size="sm">
                    <UInputNumber
                      v-model="editForm.tahun_pembangunan"
                      placeholder="Contoh: 2024"
                      size="sm"
                      class="w-full"
                    />
                  </UFormField>
                </div>

                <UFormField label="Plotting Anggaran Terkait" size="sm">
                  <USelectMenu
                    v-model="editForm.plotting_id"
                    :items="plottingItems"
                    value-key="value"
                    label-key="label"
                    placeholder="Pilih plotting..."
                    size="sm"
                    class="w-full"
                  />
                </UFormField>

                <div class="grid grid-cols-2 gap-2">
                  <UFormField label="Sumber Dana" size="sm">
                    <UInput
                      v-model="editForm.sumber_dana"
                      placeholder="Contoh: APBD 2024"
                      size="sm"
                      class="w-full"
                    />
                  </UFormField>

                  <UFormField label="Status Aset" size="sm">
                    <UInput
                      v-model="editForm.status_aset"
                      placeholder="Contoh: Aset Desa"
                      size="sm"
                      class="w-full"
                    />
                  </UFormField>
                </div>

                <UFormField label="Keterangan Tambahan" size="sm">
                  <UTextarea
                    v-model="editForm.keterangan"
                    :rows="2"
                    placeholder="Catatan kondisi atau spesifikasi..."
                    size="sm"
                    class="w-full"
                  />
                </UFormField>

                <UButton
                  label="Simpan Perubahan"
                  icon="i-lucide-save"
                  color="primary"
                  variant="solid"
                  class="w-full justify-center mt-2 cursor-pointer"
                  :loading="loading"
                  @click="handleSaveEdit"
                />
              </div>
            </template>
          </div>
        </div>

        <!-- Sticky Footer: Koordinat Klik Map Canvas -->
        <div class="border-t border-gray-200 dark:border-gray-800 shrink-0 px-3 py-2 bg-gray-50/50 dark:bg-gray-900/30">
          <div
            v-if="clickedCoordinate"
            class="flex items-center justify-between gap-2 text-xs"
          >
            <div class="flex items-center gap-1.5 font-mono text-[11px] text-gray-700 dark:text-gray-300 min-w-0">
              <span class="truncate">
                <span class="text-gray-400 dark:text-gray-500 font-sans text-[10px] mr-1">Lat</span>{{ clickedCoordinate[1].toFixed(6) }},
                <span class="text-gray-400 dark:text-gray-500 font-sans text-[10px] mr-1 ml-1.5">Long</span>{{ clickedCoordinate[0].toFixed(6) }}
              </span>
            </div>

            <UTooltip :text="copied ? 'Tersalin!' : 'Salin koordinat'">
              <UButton
                :icon="copied ? 'i-lucide-check' : 'i-lucide-copy'"
                size="xs"
                :color="copied ? 'primary' : 'neutral'"
                variant="ghost"
                class="cursor-pointer shrink-0 h-6 px-1.5 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                @click="copyCoords"
              />
            </UTooltip>
          </div>

          <div
            v-else
            class="flex items-center gap-1.5 text-[11px] text-gray-400 dark:text-gray-500 py-0.5"
          >
            <UIcon name="i-lucide-mouse-pointer-click" class="size-3.5 shrink-0 opacity-50" />
            <span class="italic text-[11px]">Klik peta untuk mengambil koordinat</span>
          </div>
        </div>
      </div>
    </Transition>
  </component>
</template>

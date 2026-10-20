<script setup lang="ts">
import * as XLSX from 'xlsx';
import type { RefBkkdBidang } from '~/types/bkkd';
import { useBkkdApi } from '~/composables/useBkkdApi';

interface Props {
  open: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  open: false,
});

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
  (e: 'success'): void;
}>();

const toast = useToast();
const api = useBkkdApi();

const isOpen = computed({
  get: () => props.open,
  set: (val: boolean) => emit('update:open', val),
});

interface ParsedRow {
  index: number;
  tahun_anggaran: number;
  nama_kecamatan: string;
  nama_desa: string;
  nama_bidang: string;
  isInferredBidang?: boolean;
  judul_kegiatan: string;
  nilai_usulan: number | null;
  panjang: number | null;
  lebar: number | null;
  tinggi: number | null;
  volume: number | null;
  satuan: string;
  lokasi_kegiatan: string;
  uraian_kegiatan: string;
  nomor_surat_desa: string;
  tanggal_surat_desa: string;
  isValid: boolean;
  errors: string[];
}

// Master Bidang State
const bidangList = ref<RefBkkdBidang[]>([]);
const loadingBidang = ref(false);

async function loadMasterBidang() {
  if (bidangList.value.length > 0) return;
  loadingBidang.value = true;
  try {
    const res = await api.fetchRefBidang();
    bidangList.value = res.data || [];
  } catch (err) {
    console.error('Gagal memuat master bidang:', err);
  } finally {
    loadingBidang.value = false;
  }
}

watch(isOpen, (newVal) => {
  if (newVal) {
    loadMasterBidang();
  }
});

onMounted(() => {
  loadMasterBidang();
});

// State
const isDragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const selectedFileName = ref('');
const parsedRows = ref<ParsedRow[]>([]);
const isParsing = ref(false);
const isSubmitting = ref(false);
const activeFilter = ref<'all' | 'valid' | 'invalid'>('all');

// Result state after submission
const importResult = ref<{
  success: boolean;
  imported_count: number;
  failed_count: number;
  errors: Array<{ baris: number; judul: string; pesan: string }>;
} | null>(null);

const validRowsCount = computed(() => parsedRows.value.filter(r => r.isValid).length);
const invalidRowsCount = computed(() => parsedRows.value.filter(r => !r.isValid).length);

const filteredRows = computed(() => {
  if (activeFilter.value === 'valid') {
    return parsedRows.value.filter(r => r.isValid);
  }
  if (activeFilter.value === 'invalid') {
    return parsedRows.value.filter(r => !r.isValid);
  }
  return parsedRows.value;
});

function formatCurrency(val?: number | null) {
  if (val === null || val === undefined || isNaN(val)) return '-';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val);
}

function formatDimensi(p?: number | null, l?: number | null, t?: number | null) {
  const parts = [];
  if (p !== null && p !== undefined) parts.push(`P: ${p}m`);
  if (l !== null && l !== undefined) parts.push(`L: ${l}m`);
  if (t !== null && t !== undefined) parts.push(`T: ${t}m`);
  return parts.length ? parts.join(' | ') : '-';
}

/**
 * Resolusi Cerdas Bidang BKKD:
 * 1. Cocokkan nama persis (case-insensitive)
 * 2. Cocokkan nama tanpa prefix 'BKK' atau 'Bidang'
 * 3. Kamus kata kunci / alias bidang
 * 4. Inferensi otomatis dari Judul Kegiatan jika kolom bidang kosong atau umum
 */
function resolveBidangSmart(rawBidang: string, judul: string): { nama: string; isInferred: boolean } {
  const cleanInput = (rawBidang || '').trim();
  const cleanJudul = (judul || '').toLowerCase();

  // 1. Exact match dengan master bidang
  if (cleanInput && bidangList.value.length > 0) {
    const exact = bidangList.value.find(
      b => b.nama.toLowerCase() === cleanInput.toLowerCase()
    );
    if (exact) return { nama: exact.nama, isInferred: false };

    // 2. Prefix strip match (misal input 'Jalan' atau 'Jembatan')
    const withoutPrefix = cleanInput.replace(/^(bkk|bidang)\s+/i, '').toLowerCase();
    const prefixMatch = bidangList.value.find(b => {
      const bNameClean = b.nama.toLowerCase();
      const bWithoutPrefix = bNameClean.replace(/^(bkk|bidang)\s+/i, '');
      return bNameClean === withoutPrefix ||
        bWithoutPrefix === withoutPrefix ||
        bNameClean.includes(withoutPrefix) ||
        withoutPrefix.includes(bWithoutPrefix);
    });
    if (prefixMatch) return { nama: prefixMatch.nama, isInferred: false };
  }

  // 3. Kamus Kata Kunci (menganalisis teks bidang & judul kegiatan)
  const combinedText = `${cleanInput} ${cleanJudul}`.toLowerCase();

  const rules: Array<{ target: string; keywords: string[] }> = [
    { target: 'BKK JEMBATAN', keywords: ['jembatan', 'box culvert', 'culvert', 'plat beton', 'flyover', 'abutment'] },
    { target: 'BKK JALAN', keywords: ['jalan', 'rabat', 'aspal', 'hotmix', 'paving', 'betonisasi', 'poros desa'] },
    { target: 'BKK TPT', keywords: ['tpt', 'talud', 'penahan tanah', 'tembok penahan', 'bronjong', 'plengsengan', 'turap'] },
    { target: 'Drainase & Sanitasi Lingkungan', keywords: ['drainase', 'saluran air', 'sanitasi', 'parit', 'gorong-gorong', 'selokan', 'spal'] },
    { target: 'Sarana & Prasarana Kantor / Balai Desa', keywords: ['balai desa', 'kantor desa', 'pendopo', 'gedung serbaguna', 'pagar kantor'] },
    { target: 'Pemberdayaan Ekonomi & BUMDes', keywords: ['bumdes', 'pasar desa', 'kios', 'wisata desa', 'pujasera', 'ekonomi desa'] },
    { target: 'Kesehatan Masyarakat & Posyandu', keywords: ['posyandu', 'polindes', 'ponkesdes', 'pustu', 'kesehatan', 'stunting', 'mck'] },
    { target: 'Jalan Usaha Tani & Ketahanan Pangan', keywords: ['jut', 'jalan usaha tani', 'usaha tani', 'irigasi tersier', 'irigasi desa', 'embung'] },
  ];

  for (const rule of rules) {
    for (const kw of rule.keywords) {
      if (combinedText.includes(kw)) {
        const found = bidangList.value.find(b => b.nama.toLowerCase() === rule.target.toLowerCase());
        if (found) {
          const wasInferred = !cleanInput || !cleanInput.toLowerCase().includes(kw);
          return { nama: found.nama, isInferred: wasInferred };
        }
      }
    }
  }

  // Fallback: Default ke bidang pertama atau BKK JALAN
  const defaultBidang = bidangList.value.find(b => b.nama === 'BKK JALAN') || bidangList.value[0];
  return {
    nama: defaultBidang ? defaultBidang.nama : (cleanInput || 'BKK JALAN'),
    isInferred: !cleanInput,
  };
}

/**
 * Parsing Tanggal Multi-Format (DD/MM/YYYY, DD-MM-YYYY, YYYY-MM-DD, Excel Serial)
 */
function parseDateSmart(val: any): string {
  if (val === null || val === undefined || val === '') {
    return new Date().toISOString().split('T')[0];
  }

  // Jika Excel serial number (misal: 46079)
  if (typeof val === 'number' && val > 20000 && val < 100000) {
    const date = new Date(Math.round((val - 25569) * 86400 * 1000));
    return date.toISOString().split('T')[0];
  }

  const str = String(val).trim();

  // Format DD/MM/YYYY atau DD-MM-YYYY
  const dmyMatch = str.match(/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/);
  if (dmyMatch) {
    const day = dmyMatch[1].padStart(2, '0');
    const month = dmyMatch[2].padStart(2, '0');
    const year = dmyMatch[3];
    return `${year}-${month}-${day}`;
  }

  // Format YYYY-MM-DD
  const ymdMatch = str.match(/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/);
  if (ymdMatch) {
    const year = ymdMatch[1];
    const month = ymdMatch[2].padStart(2, '0');
    const day = ymdMatch[3].padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  const parsed = Date.parse(str);
  if (!isNaN(parsed)) {
    return new Date(parsed).toISOString().split('T')[0];
  }

  return new Date().toISOString().split('T')[0];
}

function downloadExcelTemplate() {
  const headers = [
    'Tahun Anggaran',
    'Kecamatan',
    'Desa',
    'Bidang',
    'Judul Kegiatan',
    'Nilai Usulan (Rp)',
    'Panjang (m)',
    'Lebar (m)',
    'Tinggi (m)',
    'Volume',
    'Satuan',
    'Lokasi Kegiatan',
    'Uraian Kegiatan',
    'Nomor Surat Desa',
    'Tanggal Surat Desa',
  ];

  const sampleRows = [
    [
      2027,
      'Kanor',
      'Pilang',
      'BKK JALAN',
      'Pembangunan Jalan Rabat Beton Dusun Krajan RT 01 RW 02',
      250000000,
      1500,
      3.5,
      0.2,
      1500,
      'meter',
      'Dusun Krajan',
      'Pembangunan jalan poros desa untuk kelancaran mobilitas warga dan distribusi panen',
      '400/01/2026',
      '26/02/2026',
    ],
    [
      2027,
      'Kanor',
      'Pilang',
      'BKK JEMBATAN',
      'Pembangunan Jembatan Usaha Tani Dusun Sambong',
      350000000,
      12,
      4.0,
      null,
      1,
      'unit',
      'Dusun Sambong',
      'Pembangunan jembatan penghubung jalan usaha tani antardusun',
      '400/02/2026',
      '26/02/2026',
    ],
    [
      2027,
      'Kanor',
      'Pilang',
      'BKK TPT',
      'Pembangunan Tembok Penahan Tanah Bantaran Kali Pilang',
      180000000,
      250,
      0.4,
      1.8,
      250,
      'meter',
      'Bantaran Kali Pilang',
      'Penguatan tebing jalan desa untuk mencegah longsor saat musim hujan',
      '400/03/2026',
      '26/02/2026',
    ],
  ];

  const ws = XLSX.utils.aoa_to_sheet([headers, ...sampleRows]);
  ws['!cols'] = [
    { wch: 15 },
    { wch: 18 },
    { wch: 18 },
    { wch: 22 },
    { wch: 45 },
    { wch: 18 },
    { wch: 12 },
    { wch: 12 },
    { wch: 12 },
    { wch: 10 },
    { wch: 10 },
    { wch: 25 },
    { wch: 45 },
    { wch: 20 },
    { wch: 18 },
  ];

  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Template Proposal');

  // Sheet 2: Daftar Referensi Bidang Resmi
  const refHeaders = ['Nama Bidang Resmi BKKD', 'Kategori Pekerjaan', 'Contoh Kata Kunci / Usulan'];
  const refRows = [
    ['BKK JALAN', 'Infrastruktur Jalan (Dinas PUBMPR)', 'Rabat beton, aspal hotmix, paving block jalan poros'],
    ['BKK JEMBATAN', 'Infrastruktur Jembatan (Dinas PUBMPR)', 'Jembatan beton, box culvert, plat deker, penghubung'],
    ['BKK TPT', 'Tembok Penahan Tanah (Dinas PUBMPR)', 'TPT, talud jalan, bronjong batu kali, plengsengan tebing'],
    ['Drainase & Sanitasi Lingkungan', 'Saluran Pembuangan (Dinas PUBMPR)', 'Drainase pemukiman, parit lingkungan, gorong-gorong, sanitasi'],
    ['Sarana & Prasarana Balai Desa', 'Gedung & Kantor (DPMD)', 'Pembangunan/rehab balai desa, pendopo, gedung pertemuan'],
    ['Pemberdayaan Ekonomi & BUMDes', 'Ekonomi Desa (DPMD)', 'Pasar desa, kios BUMDes, sarana usaha desa'],
    ['Kesehatan Masyarakat & Posyandu', 'Fasilitas Kesehatan (Dinkes)', 'Gedung posyandu, polindes, pengentasan stunting'],
    ['Jalan Usaha Tani & Ketahanan Pangan', 'Pertanian & Irigasi (Distan)', 'Jalan usaha tani (JUT), saluran irigasi tersier, embung'],
  ];

  const wsRef = XLSX.utils.aoa_to_sheet([refHeaders, ...refRows]);
  wsRef['!cols'] = [{ wch: 35 }, { wch: 35 }, { wch: 60 }];
  XLSX.utils.book_append_sheet(wb, wsRef, 'Referensi Bidang');

  XLSX.writeFile(wb, 'Template_Import_Proposal_BKKD.xlsx');
}

function downloadCsvTemplate() {
  window.open(api.getProposalImportTemplateUrl(), '_blank');
}

function handleDragOver(e: DragEvent) {
  e.preventDefault();
  isDragging.value = true;
}

function handleDragLeave(e: DragEvent) {
  e.preventDefault();
  isDragging.value = false;
}

function handleDrop(e: DragEvent) {
  e.preventDefault();
  isDragging.value = false;
  const files = e.dataTransfer?.files;
  if (files && files.length > 0) {
    processFile(files[0]);
  }
}

function handleFileSelect(e: Event) {
  const target = e.target as HTMLInputElement;
  if (target.files && target.files.length > 0) {
    processFile(target.files[0]);
  }
}

async function processFile(file: File) {
  const validExtensions = ['.xlsx', '.xls', '.csv'];
  const ext = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();
  if (!validExtensions.includes(ext)) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Format File Tidak Sesuai',
      description: 'Harap unggah file spreadsheet berekstensi .xlsx, .xls, atau .csv.',
      color: 'error',
    });
    return;
  }

  selectedFileName.value = file.name;
  isParsing.value = true;
  importResult.value = null;

  try {
    const data = await file.arrayBuffer();
    const workbook = XLSX.read(data, { type: 'array' });
    const firstSheetName = workbook.SheetNames[0];
    const worksheet = workbook.Sheets[firstSheetName];
    const rawRows = XLSX.utils.sheet_to_json<Record<string, any>>(worksheet, { defval: '' });

    if (!rawRows || rawRows.length === 0) {
      toast.add({
        icon: 'i-lucide-alert-circle',
        title: 'File Kosong',
        description: 'Tidak ada baris data yang ditemukan pada lembar kerja pertama.',
        color: 'warning',
      });
      parsedRows.value = [];
      return;
    }

    const rows: ParsedRow[] = [];

    rawRows.forEach((row, i) => {
      // Find field values with flexible key naming
      const getVal = (aliases: string[]) => {
        for (const alias of aliases) {
          if (row[alias] !== undefined && row[alias] !== null && String(row[alias]).trim() !== '') {
            return row[alias];
          }
        }
        return '';
      };

      const judul = String(getVal(['Judul Kegiatan', 'judul_kegiatan', 'Judul', 'judul', 'Kegiatan'])).trim();
      const kecamatan = String(getVal(['Kecamatan', 'nama_kecamatan', 'kecamatan'])).trim();
      const desa = String(getVal(['Desa', 'nama_desa', 'desa'])).trim();
      const bidangRaw = String(getVal(['Bidang', 'nama_bidang', 'bidang'])).trim();
      const tahunRaw = getVal(['Tahun Anggaran', 'tahun_anggaran', 'Tahun', 'tahun']);
      const nilaiRaw = getVal(['Nilai Usulan (Rp)', 'Nilai Usulan', 'nilai_usulan', 'Nilai', 'nilai']);
      const panjangRaw = getVal(['Panjang (m)', 'Panjang', 'panjang']);
      const lebarRaw = getVal(['Lebar (m)', 'Lebar', 'lebar']);
      const tinggiRaw = getVal(['Tinggi (m)', 'Tinggi', 'tinggi']);
      const volumeRaw = getVal(['Volume', 'volume']);
      const satuan = String(getVal(['Satuan', 'satuan'])).trim();
      const lokasi = String(getVal(['Lokasi Kegiatan', 'lokasi_kegiatan', 'Lokasi', 'lokasi'])).trim();
      const uraian = String(getVal(['Uraian Kegiatan', 'uraian_kegiatan', 'Uraian', 'uraian'])).trim();
      const noSurat = String(getVal(['Nomor Surat Desa', 'nomor_surat_desa', 'nomor_surat'])).trim();
      const tglSuratRaw = getVal(['Tanggal Surat Desa', 'tanggal_surat_desa', 'tanggal_surat']);

      // Skip row if it looks completely empty
      if (!judul && !kecamatan && !desa && !nilaiRaw) {
        return;
      }

      // Parse numerical fields
      const parseNumber = (val: any): number | null => {
        if (val === '' || val === null || val === undefined) return null;
        if (typeof val === 'number') return val;
        const cleaned = String(val).replace(/[^0-9.-]/g, '');
        const n = parseFloat(cleaned);
        return isNaN(n) ? null : n;
      };

      const parseTahun = (val: any): number => {
        const n = parseNumber(val);
        if (n && n >= 2020 && n <= 2100) return Math.floor(n);
        return new Date().getFullYear() + 1;
      };

      const tahun = parseTahun(tahunRaw);
      const nilai = parseNumber(nilaiRaw);
      const panjang = parseNumber(panjangRaw);
      const lebar = parseNumber(lebarRaw);
      const tinggi = parseNumber(tinggiRaw);
      const volume = parseNumber(volumeRaw);

      // Resolusi Cerdas Bidang
      const bidangResolved = resolveBidangSmart(bidangRaw, judul);

      // Parsing Tanggal Pintar
      const tanggalSurat = parseDateSmart(tglSuratRaw);

      // Validation
      const rowErrors: string[] = [];
      if (!judul) rowErrors.push('Judul kegiatan wajib diisi');
      if (!kecamatan) rowErrors.push('Kecamatan wajib diisi');
      if (!desa) rowErrors.push('Desa wajib diisi');
      if (nilaiRaw !== '' && nilai === null) rowErrors.push('Format nilai usulan tidak valid');

      rows.push({
        index: i + 1,
        tahun_anggaran: tahun,
        nama_kecamatan: kecamatan,
        nama_desa: desa,
        nama_bidang: bidangResolved.nama,
        isInferredBidang: bidangResolved.isInferred,
        judul_kegiatan: judul,
        nilai_usulan: nilai,
        panjang,
        lebar,
        tinggi,
        volume,
        satuan,
        lokasi_kegiatan: lokasi,
        uraian_kegiatan: uraian,
        nomor_surat_desa: noSurat,
        tanggal_surat_desa: tanggalSurat,
        isValid: rowErrors.length === 0,
        errors: rowErrors,
      });
    });

    parsedRows.value = rows;
    activeFilter.value = 'all';

    if (rows.length === 0) {
      toast.add({
        icon: 'i-lucide-info',
        title: 'Tidak Ada Data',
        description: 'Tidak ada baris data usulan yang valid untuk diproses.',
        color: 'warning',
      });
    }
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Membaca File',
      description: err?.message || 'Pastikan file spreadsheet Anda tidak rusak atau terkunci password.',
      color: 'error',
    });
  } finally {
    isParsing.value = false;
    if (fileInput.value) {
      fileInput.value.value = '';
    }
  }
}

function resetUpload() {
  parsedRows.value = [];
  selectedFileName.value = '';
  importResult.value = null;
  activeFilter.value = 'all';
}

async function submitImport() {
  const validRows = parsedRows.value.filter(r => r.isValid);
  if (validRows.length === 0) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Tidak Ada Baris Valid',
      description: 'Perbaiki baris yang bermasalah atau unggah file dengan format yang sesuai.',
      color: 'warning',
    });
    return;
  }

  isSubmitting.value = true;
  try {
    const payloadRows = validRows.map(r => ({
      tahun_anggaran: r.tahun_anggaran,
      nama_kecamatan: r.nama_kecamatan,
      nama_desa: r.nama_desa,
      nama_bidang: r.nama_bidang,
      judul_kegiatan: r.judul_kegiatan,
      nilai_usulan: r.nilai_usulan,
      panjang: r.panjang,
      lebar: r.lebar,
      tinggi: r.tinggi,
      volume: r.volume,
      satuan: r.satuan,
      lokasi_kegiatan: r.lokasi_kegiatan,
      uraian_kegiatan: r.uraian_kegiatan,
      nomor_surat_desa: r.nomor_surat_desa,
      tanggal_surat_desa: r.tanggal_surat_desa,
    }));

    const res = await api.importProposalExcel({ rows: payloadRows });

    importResult.value = {
      success: true,
      imported_count: res.imported_count || 0,
      failed_count: res.failed_count || 0,
      errors: res.errors || [],
    };

    toast.add({
      icon: 'i-lucide-check-circle',
      title: 'Import Selesai',
      description: res.message || `${res.imported_count} proposal berhasil diimport.`,
      color: 'success',
    });

    emit('success');
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Import Gagal',
      description: err?.data?.message || 'Terjadi kesalahan sistem saat menyimpan usulan proposal.',
      color: 'error',
    });
  } finally {
    isSubmitting.value = false;
  }
}

function handleClose() {
  isOpen.value = false;
  resetUpload();
}
</script>

<template>
  <UModal
    v-model:open="isOpen"
    title="Import Usulan Proposal BKKD"
    description="Unggah berkas spreadsheet Excel atau CSV untuk menambahkan data usulan proposal BKKD secara massal."
    :ui="{ content: 'sm:max-w-4xl dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
  >
    <template #body>
      <div class="space-y-4 py-1">
        <!-- TAMPILAN 1: Form Upload & Petunjuk Download Template -->
        <div v-if="parsedRows.length === 0 && !importResult" class="space-y-4">
          <!-- Banner Petunjuk & Download Template -->
          <div class="p-4 rounded-xl border border-primary-200/60 dark:border-primary-900/40 bg-primary-50/40 dark:bg-primary-950/20 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div class="flex items-start gap-3">
              <div class="w-9 h-9 rounded-lg bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center shrink-0 text-primary-600 dark:text-primary-400">
                <UIcon name="i-lucide-file-spreadsheet" class="w-5 h-5" />
              </div>
              <div>
                <h4 class="text-xs font-bold text-gray-900 dark:text-gray-100">
                  Unduh Format Template BKKD
                </h4>
                <p class="text-[11px] text-gray-600 dark:text-gray-400 mt-0.5">
                  Template Excel dilengkapi lembar <strong>Referensi Bidang</strong> resmi (BKK JALAN, BKK JEMBATAN, BKK TPT, dll).
                </p>
              </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
              <UButton
                color="primary"
                variant="solid"
                icon="i-lucide-download"
                label="Unduh Excel (.xlsx)"
                size="xs"
                @click="downloadExcelTemplate"
              />
              <UButton
                color="neutral"
                variant="outline"
                icon="i-lucide-file-text"
                label="Unduh CSV"
                size="xs"
                @click="downloadCsvTemplate"
              />
            </div>
          </div>

          <!-- Dropzone Area -->
          <div
            class="border-2 border-dashed rounded-2xl p-8 flex flex-col items-center justify-center text-center transition-colors cursor-pointer"
            :class="[
              isDragging
                ? 'border-primary-500 bg-primary-50/20 dark:bg-primary-950/20'
                : 'border-gray-200 dark:border-white/[0.1] hover:border-gray-300 dark:hover:border-white/[0.2] bg-gray-50/40 dark:bg-gray-900/20'
            ]"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            @drop="handleDrop"
            @click="fileInput?.click()"
          >
            <input
              ref="fileInput"
              type="file"
              accept=".xlsx,.xls,.csv"
              class="hidden"
              @change="handleFileSelect"
            >

            <div class="w-12 h-12 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-500 dark:text-gray-400 mb-3 shadow-xs">
              <UIcon v-if="!isParsing" name="i-lucide-upload-cloud" class="w-6 h-6" />
              <UIcon v-else name="i-lucide-loader" class="w-6 h-6 animate-spin text-primary-500" />
            </div>

            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">
              {{ isParsing ? 'Menganalisis Berkas...' : 'Pilih Berkas atau Tarik ke Sini' }}
            </h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 max-w-sm mb-4">
              Mendukung berkas Microsoft Excel (<strong>.xlsx</strong>, <strong>.xls</strong>) atau file Comma Separated (<strong>.csv</strong>)
            </p>

            <UButton
              color="neutral"
              variant="outline"
              size="xs"
              icon="i-lucide-folder-open"
              label="Jelajahi Berkas Komputer"
              :loading="isParsing"
              @click.stop="fileInput?.click()"
            />
          </div>

          <!-- Panduan Pengisian & Referensi Cerdas -->
          <div class="p-3.5 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-gray-50/50 dark:bg-gray-900/30 text-xs text-gray-600 dark:text-gray-400 space-y-2">
            <div class="font-semibold text-gray-900 dark:text-gray-200 flex items-center gap-1.5">
              <UIcon name="i-lucide-sparkles" class="w-4 h-4 text-amber-500" />
              Fitur Resolusi Referensi Cerdas:
            </div>
            <ul class="list-disc list-inside space-y-1 text-[11px] leading-relaxed text-gray-500 dark:text-gray-400">
              <li><strong>Penyelesaian Otomatis Bidang:</strong> Sistem cerdas mengenali bidang seperti <code class="px-1 py-0.5 rounded bg-gray-200 dark:bg-gray-800 text-gray-800 dark:text-gray-200 font-mono">BKK JALAN</code>, <code class="px-1 py-0.5 rounded bg-gray-200 dark:bg-gray-800 text-gray-800 dark:text-gray-200 font-mono">BKK JEMBATAN</code>, <code class="px-1 py-0.5 rounded bg-gray-200 dark:bg-gray-800 text-gray-800 dark:text-gray-200 font-mono">BKK TPT</code>, dll. Bahkan jika kolom bidang dikosongkan, sistem dapat mendeteksi kata kunci dari <em>Judul Kegiatan</em>.</li>
              <li><strong>Fleksibilitas Tanggal:</strong> Mendukung format Indonesia (<code class="px-1 py-0.5 rounded bg-gray-200 dark:bg-gray-800 text-gray-800 dark:text-gray-200 font-mono">26/02/2026</code>), standar (<code class="px-1 py-0.5 rounded bg-gray-200 dark:bg-gray-800 text-gray-800 dark:text-gray-200 font-mono">2026-02-26</code>), maupun angka tanggal Excel.</li>
              <li><strong>Wilayah Desa & Kecamatan:</strong> Nama desa dan kecamatan akan dicocokkan otomatis dengan data master wilayah Bojonegoro.</li>
            </ul>
          </div>
        </div>

        <!-- TAMPILAN 2: Preview Hasil Parsing & Validasi -->
        <div v-else-if="parsedRows.length > 0 && !importResult" class="space-y-3">
          <!-- Info Baris & Filter -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-3 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-gray-50/70 dark:bg-gray-900/40">
            <div class="flex items-center gap-2">
              <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                Berkas: <span class="font-mono text-primary-600 dark:text-primary-400">{{ selectedFileName }}</span>
              </span>
              <span class="text-xs text-gray-400">({{ parsedRows.length }} baris)</span>
            </div>

            <div class="flex items-center gap-1.5">
              <button
                type="button"
                class="px-2.5 py-1 rounded-md text-[11px] font-medium transition-colors"
                :class="activeFilter === 'all' ? 'bg-gray-200 dark:bg-gray-800 text-gray-900 dark:text-gray-100' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                @click="activeFilter = 'all'"
              >
                Semua ({{ parsedRows.length }})
              </button>
              <button
                type="button"
                class="px-2.5 py-1 rounded-md text-[11px] font-medium transition-colors"
                :class="activeFilter === 'valid' ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' : 'text-gray-500 hover:text-emerald-600'"
                @click="activeFilter = 'valid'"
              >
                Siap Import ({{ validRowsCount }})
              </button>
              <button
                v-if="invalidRowsCount > 0"
                type="button"
                class="px-2.5 py-1 rounded-md text-[11px] font-medium transition-colors"
                :class="activeFilter === 'invalid' ? 'bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300' : 'text-gray-500 hover:text-rose-600'"
                @click="activeFilter = 'invalid'"
              >
                Bermasalah ({{ invalidRowsCount }})
              </button>
              <UButton
                color="neutral"
                variant="ghost"
                size="xs"
                icon="i-lucide-refresh-cw"
                label="Ganti Berkas"
                @click="resetUpload"
              />
            </div>
          </div>

          <!-- Alert jika ada data invalid -->
          <div
            v-if="invalidRowsCount > 0"
            class="p-2.5 rounded-lg border border-amber-200 dark:border-amber-900/50 bg-amber-50/60 dark:bg-amber-950/30 flex items-center justify-between text-xs text-amber-800 dark:text-amber-200"
          >
            <div class="flex items-center gap-2">
              <UIcon name="i-lucide-alert-triangle" class="w-4 h-4 shrink-0 text-amber-600 dark:text-amber-400" />
              <span>
                Ditemukan <strong>{{ invalidRowsCount }}</strong> baris bermasalah. Baris bermasalah otomatis dilewati saat import.
              </span>
            </div>
          </div>

          <!-- Tabel Preview Scrollable -->
          <div class="border border-gray-100 dark:border-white/[0.08] rounded-xl overflow-hidden max-h-[380px] overflow-y-auto">
            <table class="w-full text-left text-xs">
              <thead class="sticky top-0 bg-gray-50 dark:bg-[#121826] border-b border-gray-100 dark:border-white/[0.08] text-gray-500 dark:text-gray-400 text-[11px] uppercase tracking-wider font-semibold z-10">
                <tr>
                  <th class="py-2.5 px-3 w-12 text-center">No</th>
                  <th class="py-2.5 px-3 w-28">Status</th>
                  <th class="py-2.5 px-3 w-36">Wilayah</th>
                  <th class="py-2.5 px-3 w-48">Bidang</th>
                  <th class="py-2.5 px-3 min-w-[200px]">Judul Kegiatan</th>
                  <th class="py-2.5 px-3 text-right">Nilai Usulan</th>
                  <th class="py-2.5 px-3">Dimensi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-white/[0.05] text-gray-700 dark:text-gray-300">
                <tr
                  v-for="row in filteredRows"
                  :key="row.index"
                  class="transition-colors hover:bg-gray-50/50 dark:hover:bg-white/[0.02]"
                  :class="!row.isValid ? 'bg-rose-50/30 dark:bg-rose-950/10' : ''"
                >
                  <td class="py-2.5 px-3 text-center font-mono text-[11px] text-gray-400">
                    {{ row.index }}
                  </td>
                  <td class="py-2.5 px-3">
                    <span
                      v-if="row.isValid"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300"
                    >
                      <UIcon name="i-lucide-check" class="w-3 h-3" />
                      Siap
                    </span>
                    <span
                      v-else
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300"
                      :title="row.errors.join(', ')"
                    >
                      <UIcon name="i-lucide-x" class="w-3 h-3" />
                      {{ row.errors[0] }}
                    </span>
                  </td>
                  <td class="py-2.5 px-3">
                    <div class="font-medium text-gray-900 dark:text-gray-100">
                      {{ row.nama_desa || '-' }}
                    </div>
                    <div class="text-[10px] text-gray-400">
                      Kec. {{ row.nama_kecamatan || '-' }}
                    </div>
                  </td>
                  <td class="py-2.5 px-3">
                    <div class="flex flex-col gap-1">
                      <select
                        v-model="row.nama_bidang"
                        class="text-[11px] py-1 px-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-primary-500 font-medium"
                      >
                        <option v-for="b in bidangList" :key="b.id" :value="b.nama">
                          {{ b.nama }}
                        </option>
                        <option v-if="!bidangList.some(b => b.nama === row.nama_bidang)" :value="row.nama_bidang">
                          {{ row.nama_bidang }}
                        </option>
                      </select>
                      <span
                        v-if="row.isInferredBidang"
                        class="text-[9px] text-primary-600 dark:text-primary-400 font-medium flex items-center gap-0.5"
                      >
                        <UIcon name="i-lucide-sparkles" class="w-2.5 h-2.5" />
                        Inferred otomatis
                      </span>
                    </div>
                  </td>
                  <td class="py-2.5 px-3">
                    <div class="font-medium text-gray-900 dark:text-gray-100 line-clamp-2">
                      {{ row.judul_kegiatan || '-' }}
                    </div>
                    <div v-if="row.lokasi_kegiatan" class="text-[10px] text-gray-400 line-clamp-1 mt-0.5">
                      Lokasi: {{ row.lokasi_kegiatan }}
                    </div>
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono font-medium text-emerald-600 dark:text-emerald-400">
                    {{ formatCurrency(row.nilai_usulan) }}
                  </td>
                  <td class="py-2.5 px-3 font-mono text-[11px] text-gray-500 dark:text-gray-400 whitespace-nowrap">
                    {{ formatDimensi(row.panjang, row.lebar, row.tinggi) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- TAMPILAN 3: Ringkasan Hasil Setelah Import -->
        <div v-else-if="importResult" class="space-y-4">
          <div class="p-4 rounded-xl border border-gray-100 dark:border-white/[0.08] bg-gray-50/70 dark:bg-gray-900/40 text-center space-y-2">
            <div class="w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto">
              <UIcon name="i-lucide-check-circle-2" class="w-7 h-7" />
            </div>
            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">
              Proses Import Usulan Selesai
            </h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 max-w-md mx-auto">
              Data proposal yang berhasil diimport telah masuk ke sistem dengan status <strong>Draft</strong> dan nomor registrasi resmi.
            </p>

            <div class="grid grid-cols-2 gap-3 max-w-sm mx-auto pt-2">
              <div class="p-3 rounded-lg border border-emerald-200/60 dark:border-emerald-900/40 bg-emerald-50/50 dark:bg-emerald-950/20">
                <span class="text-[10px] uppercase font-bold text-emerald-700 dark:text-emerald-400">Berhasil Diimport</span>
                <div class="text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-0.5">
                  {{ importResult.imported_count }}
                </div>
              </div>
              <div class="p-3 rounded-lg border border-rose-200/60 dark:border-rose-900/40 bg-rose-50/50 dark:bg-rose-950/20">
                <span class="text-[10px] uppercase font-bold text-rose-700 dark:text-rose-400">Gagal / Dilewati</span>
                <div class="text-2xl font-bold font-mono text-rose-600 dark:text-rose-400 mt-0.5">
                  {{ importResult.failed_count }}
                </div>
              </div>
            </div>
          </div>

          <!-- Daftar Rincian Baris yang Gagal / Dilewati jika ada -->
          <div v-if="importResult.errors && importResult.errors.length > 0" class="space-y-2">
            <h4 class="text-xs font-semibold text-rose-600 dark:text-rose-400 flex items-center gap-1.5">
              <UIcon name="i-lucide-alert-circle" class="w-4 h-4" />
              Rincian Baris yang Dilewati ({{ importResult.errors.length }}):
            </h4>
            <div class="border border-rose-200/60 dark:border-rose-900/40 rounded-xl overflow-hidden max-h-48 overflow-y-auto">
              <table class="w-full text-left text-xs">
                <thead class="bg-rose-50/60 dark:bg-rose-950/40 text-[10px] uppercase font-bold text-rose-700 dark:text-rose-300">
                  <tr>
                    <th class="py-2 px-3 w-16 text-center">Baris</th>
                    <th class="py-2 px-3">Judul Kegiatan</th>
                    <th class="py-2 px-3">Alasan / Catatan Kendala</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-rose-100 dark:divide-rose-900/30 text-gray-700 dark:text-gray-300">
                  <tr v-for="(err, idx) in importResult.errors" :key="idx">
                    <td class="py-2 px-3 text-center font-mono font-bold text-rose-600 dark:text-rose-400">
                      #{{ err.baris }}
                    </td>
                    <td class="py-2 px-3 font-medium">
                      {{ err.judul || '-' }}
                    </td>
                    <td class="py-2 px-3 text-rose-600 dark:text-rose-400">
                      {{ err.pesan }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </template>

    <template #footer>
      <div class="flex items-center justify-between w-full">
        <!-- Tombol Batal / Kembali -->
        <UButton
          v-if="!importResult"
          color="neutral"
          variant="ghost"
          label="Batal"
          :disabled="isSubmitting"
          @click="handleClose"
        />
        <div v-else />

        <!-- Tombol Aksi Kanan -->
        <div class="flex items-center gap-2">
          <!-- Step 1: Belum ada file -->
          <template v-if="parsedRows.length === 0 && !importResult">
            <UButton
              color="primary"
              variant="soft"
              icon="i-lucide-folder-open"
              label="Pilih Berkas Spreadsheet"
              @click="fileInput?.click()"
            />
          </template>

          <!-- Step 2: Ada file preview -->
          <template v-else-if="parsedRows.length > 0 && !importResult">
            <UButton
              color="neutral"
              variant="outline"
              label="Reset / Unggah Ulang"
              :disabled="isSubmitting"
              @click="resetUpload"
            />
            <UButton
              color="primary"
              icon="i-lucide-upload"
              :label="`Import ${validRowsCount} Usulan Sekarang`"
              :disabled="validRowsCount === 0 || isSubmitting"
              :loading="isSubmitting"
              @click="submitImport"
            />
          </template>

          <!-- Step 3: Hasil import selesai -->
          <template v-else-if="importResult">
            <UButton
              color="primary"
              icon="i-lucide-check"
              label="Tutup & Muat Ulang Halaman"
              @click="handleClose"
            />
          </template>
        </div>
      </div>
    </template>
  </UModal>
</template>

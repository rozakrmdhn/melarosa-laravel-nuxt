<script setup lang="ts">
import type {
  KategoriJalan,
  RekapKecamatanItem,
  RekapDesaItem,
  RekapRuasItem,
  RekapSegmenItem,
  RekapFilterParams,
  RekapSummaryCards,
} from '~/types/rekap-jalan';
import ModalPreviewPeta from '~/components/rekap-jalan/ModalPreviewPeta.vue';
import * as XLSX from 'xlsx';

definePageMeta({
  middleware: ['auth', 'role-admin'],
});

useSeoMeta({
  title: 'Rekapitulasi Jalan Poros Desa & Non-Ruas',
});

const toast = useToast();
const api = useRekapJalanApi();

// ─── State ──────────────────────────────────────────────────────────────────
const activeKategori = ref<KategoriJalan>('ruas');
const searchQuery = ref('');
const selectedTahun = ref<string>('semua');
const selectedKondisi = ref<string>('semua');
const selectedSumberDana = ref<string>('semua');

const loadingKecamatan = ref(false);
const kecamatanList = ref<RekapKecamatanItem[]>([]);

// Hierarchy expansion tracking
const expandedKecamatan = ref<Set<number>>(new Set());
const expandedDesa = ref<Set<string | number>>(new Set());
const expandedRuas = ref<Set<string | number>>(new Set());

// Lazy loaded cache maps
const desaDataMap = ref<Record<number, RekapDesaItem[]>>({});
const loadingDesaMap = ref<Record<number, boolean>>({});

const ruasDataMap = ref<Record<string | number, RekapRuasItem[]>>({});
const loadingRuasMap = ref<Record<string | number, boolean>>({});

const segmenDataMap = ref<Record<string | number, RekapSegmenItem[]>>({});
const loadingSegmenMap = ref<Record<string | number, boolean>>({});

// Spatial Preview Modal
const previewModalOpen = ref(false);
const activePreviewItem = ref<RekapRuasItem | RekapSegmenItem | null>(null);

// Options for filters
const tahunOptions = [
  { label: 'Semua Tahun', value: 'semua' },
  { label: '2026', value: 2026 },
  { label: '2025', value: 2025 },
  { label: '2024', value: 2024 },
  { label: '2023', value: 2023 },
  { label: '2022', value: 2022 },
  { label: '2021', value: 2021 },
];

const kondisiOptions = [
  { label: 'Semua Kondisi', value: 'semua' },
  { label: 'Baik', value: 'baik' },
  { label: 'Sedang', value: 'sedang' },
  { label: 'Rusak Ringan', value: 'rusak ringan' },
  { label: 'Rusak Berat', value: 'rusak berat' },
];

const sumberDanaOptions = [
  { label: 'Semua Sumber Dana', value: 'semua' },
  { label: 'BKK', value: 'BKK' },
  { label: 'APBD', value: 'APBD' },
  { label: 'Dana Desa (DD)', value: 'DD' },
];

// ─── Formatters ─────────────────────────────────────────────────────────────
function formatMeterOrKm(meters?: number): string {
  if (meters === undefined || meters === null || isNaN(meters)) return '0 m';
  if (Math.abs(meters) >= 1000) {
    return `${(meters / 1000).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} km`;
  }
  return `${Math.round(meters).toLocaleString('id-ID')} m`;
}

function formatNumber(val?: number): string {
  if (val === undefined || val === null || isNaN(val)) return '0';
  return val.toLocaleString('id-ID');
}

function getKondisiBadgeClass(kondisi?: string): string {
  const k = (kondisi || '').toLowerCase();
  if (k.includes('rusak berat')) {
    return 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-800/40';
  }
  if (k.includes('rusak ringan') || k.includes('rusak')) {
    return 'bg-orange-50 text-orange-700 dark:bg-orange-950/40 dark:text-orange-400 border border-orange-200 dark:border-orange-800/40';
  }
  if (k.includes('sedang')) {
    return 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-800/40';
  }
  if (k.includes('baik')) {
    return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/40';
  }
  return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border border-gray-200 dark:border-gray-700';
}

function getProgressBarClass(progress: number): string {
  if (progress >= 80) return 'bg-emerald-500';
  if (progress >= 50) return 'bg-blue-500';
  if (progress >= 25) return 'bg-amber-500';
  return 'bg-orange-500';
}

// ─── Filtered Data ──────────────────────────────────────────────────────────
const filteredKecamatanList = computed(() => {
  let list = kecamatanList.value;
  if (!searchQuery.value.trim()) return list;

  const q = searchQuery.value.toLowerCase().trim();
  return list.filter((item) => item.nama_kecamatan.toLowerCase().includes(q));
});

// Summary Totals
const summaryTotals = computed<RekapSummaryCards>(() => {
  const items = filteredKecamatanList.value;
  let totalKec = items.length;
  let totalDesa = 0;
  let totalRuas = 0;
  let totalPanjangMeter = 0;
  let totalPanjangDibangun = 0;
  let totalPanjangBelum = 0;
  let totalSegmen = 0;

  for (const it of items) {
    totalDesa += it.jumlah_desa || 0;
    totalRuas += it.jumlah_ruas || 0;
    totalPanjangMeter += it.total_panjang || 0;
    totalPanjangDibangun += it.panjang_dibangun || 0;
    totalPanjangBelum += it.panjang_belum || 0;
    totalSegmen += it.jumlah_segmen || 0;
  }

  const rataRataProgress =
    totalPanjangMeter > 0 ? (totalPanjangDibangun / totalPanjangMeter) * 100 : 0;

  return {
    totalKecamatan: totalKec,
    totalDesa,
    totalRuas,
    totalPanjangMeter,
    totalPanjangDibangun,
    totalPanjangBelum,
    rataRataProgress: Math.min(Math.max(rataRataProgress, 0), 100),
    totalSegmen,
  };
});

// ─── Loaders ────────────────────────────────────────────────────────────────
async function loadKecamatan() {
  loadingKecamatan.value = true;
  // Clear caches on category or filter change
  expandedKecamatan.value.clear();
  expandedDesa.value.clear();
  expandedRuas.value.clear();
  desaDataMap.value = {};
  ruasDataMap.value = {};
  segmenDataMap.value = {};

  try {
    const params: RekapFilterParams = {};
    if (selectedTahun.value !== 'semua') params.tahun = Number(selectedTahun.value);
    if (selectedKondisi.value !== 'semua') params.kondisi = selectedKondisi.value;
    if (selectedSumberDana.value !== 'semua') params.sumber_dana = selectedSumberDana.value;

    kecamatanList.value = await api.fetchRekapKecamatan(activeKategori.value, params);
  } catch (err: any) {
    console.error('Gagal memuat rekap kecamatan:', err);
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Memuat Data',
      description: err?.message || 'Tidak dapat mengambil rekap data dari API external.',
      color: 'error',
    });
  } finally {
    loadingKecamatan.value = false;
  }
}

async function toggleKecamatan(kec: RekapKecamatanItem) {
  const id = kec.id_kecamatan;
  if (expandedKecamatan.value.has(id)) {
    expandedKecamatan.value.delete(id);
    return;
  }

  expandedKecamatan.value.add(id);

  if (!desaDataMap.value[id]) {
    loadingDesaMap.value[id] = true;
    try {
      const params: RekapFilterParams = {};
      if (selectedTahun.value !== 'semua') params.tahun = Number(selectedTahun.value);
      if (selectedKondisi.value !== 'semua') params.kondisi = selectedKondisi.value;
      if (selectedSumberDana.value !== 'semua') params.sumber_dana = selectedSumberDana.value;

      desaDataMap.value[id] = await api.fetchRekapDesa(id, activeKategori.value, params);
    } catch (err: any) {
      toast.add({
        icon: 'i-lucide-alert-circle',
        title: `Gagal Memuat Desa (${kec.nama_kecamatan})`,
        description: err?.message || 'Gagal mengambil data desa.',
        color: 'error',
      });
    } finally {
      loadingDesaMap.value[id] = false;
    }
  }
}

async function toggleDesa(desa: RekapDesaItem) {
  const id = desa.id_desa;
  if (expandedDesa.value.has(id)) {
    expandedDesa.value.delete(id);
    return;
  }

  expandedDesa.value.add(id);

  if (!ruasDataMap.value[id]) {
    loadingRuasMap.value[id] = true;
    try {
      const params: RekapFilterParams = {};
      if (selectedTahun.value !== 'semua') params.tahun = Number(selectedTahun.value);
      if (selectedKondisi.value !== 'semua') params.kondisi = selectedKondisi.value;
      if (selectedSumberDana.value !== 'semua') params.sumber_dana = selectedSumberDana.value;

      ruasDataMap.value[id] = await api.fetchRekapRuasByDesa(id, activeKategori.value, params);
    } catch (err: any) {
      toast.add({
        icon: 'i-lucide-alert-circle',
        title: `Gagal Memuat Ruas (${desa.nama_desa})`,
        description: err?.message || 'Gagal mengambil data ruas.',
        color: 'error',
      });
    } finally {
      loadingRuasMap.value[id] = false;
    }
  }
}

async function toggleRuas(ruas: RekapRuasItem) {
  const id = ruas.id;
  if (expandedRuas.value.has(id)) {
    expandedRuas.value.delete(id);
    return;
  }

  expandedRuas.value.add(id);

  if (!segmenDataMap.value[id]) {
    loadingSegmenMap.value[id] = true;
    try {
      segmenDataMap.value[id] = await api.fetchDetailSegmen(id);
    } catch (err: any) {
      toast.add({
        icon: 'i-lucide-alert-circle',
        title: `Gagal Memuat Detail Segmen`,
        description: err?.message || 'Gagal mengambil rincian segmen fisik.',
        color: 'error',
      });
    } finally {
      loadingSegmenMap.value[id] = false;
    }
  }
}

function openPreviewMap(
  item: RekapRuasItem | RekapSegmenItem,
  context?: { desa?: string; kecamatan?: string; nama_ruas?: string; kode_ruas?: string | number }
) {
  activePreviewItem.value = {
    ...item,
    ...(context?.desa ? { nama_desa: context.desa } : {}),
    ...(context?.kecamatan ? { nama_kecamatan: context.kecamatan } : {}),
    ...(context?.nama_ruas && !(item as any).nama_ruas ? { nama_ruas: context.nama_ruas } : {}),
    ...(context?.kode_ruas && !(item as any).kode_ruas ? { kode_ruas: context.kode_ruas } : {}),
  } as any;
  previewModalOpen.value = true;
}

function handleResetFilter() {
  searchQuery.value = '';
  selectedTahun.value = 'semua';
  selectedKondisi.value = 'semua';
  selectedSumberDana.value = 'semua';
  loadKecamatan();
}

const exportingExcel = ref(false);

function autoFitColumns(rows: any[][]) {
  const colWidths: { wch: number }[] = [];
  rows.forEach((row) => {
    row.forEach((cell, colIdx) => {
      const len = cell !== null && cell !== undefined ? String(cell).length : 0;
      if (!colWidths[colIdx] || len > colWidths[colIdx].wch) {
        colWidths[colIdx] = { wch: Math.min(65, Math.max(10, len + 3)) };
      }
    });
  });
  return colWidths;
}

async function handleExportExcel(scope: 'all' | 'kecamatan' | 'desa' = 'all') {
  if (filteredKecamatanList.value.length === 0) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Tidak Ada Data',
      description: 'Tidak ada data rekapitulasi untuk diekspor.',
      color: 'warning',
    });
    return;
  }

  exportingExcel.value = true;
  try {
    const wb = XLSX.utils.book_new();
    const kategoriLabel = activeKategori.value === 'ruas'
      ? 'Jalan Poros Desa (Dalam Database)'
      : 'Jalan Desa Non-Ruas (Diluar Database)';
    const dateStr = new Date().toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    });
    const filterInfo = `Kategori: ${kategoriLabel} | Tahun: ${selectedTahun.value} | Kondisi: ${selectedKondisi.value} | Sumber Dana: ${selectedSumberDana.value} | Tanggal Ekspor: ${dateStr}`;

    // ─── Sheet 1: Rekap Kecamatan ──────────────────────────────────────────
    if (scope === 'all' || scope === 'kecamatan') {
      const kecHeaders = [
        'No',
        'Kode Kecamatan',
        'Nama Kecamatan',
        'Jumlah Desa',
        'Jumlah Ruas / Segmen',
        'Total Panjang Master (m)',
        'Panjang Terbangun (m)',
        'Panjang Belum Terbangun (m)',
        'Panjang Master (km)',
        'Panjang Terbangun (km)',
        'Panjang Belum (km)',
        'Progress Capaian (%)',
      ];

      const kecDataRows = filteredKecamatanList.value.map((item, idx) => {
        const totalP = Number(item.total_panjang || 0);
        const pTerbangun = Number(item.panjang_dibangun || 0);
        const pBelum = Number(item.panjang_belum || 0);
        return [
          idx + 1,
          item.id_kecamatan,
          item.nama_kecamatan,
          item.jumlah_desa,
          item.jumlah_ruas,
          Math.round(totalP * 100) / 100,
          Math.round(pTerbangun * 100) / 100,
          Math.round(pBelum * 100) / 100,
          Math.round((totalP / 1000) * 100) / 100,
          Math.round((pTerbangun / 1000) * 100) / 100,
          Math.round((pBelum / 1000) * 100) / 100,
          Number(item.progress.toFixed(2)),
        ];
      });

      // Total Row
      const totDesa = filteredKecamatanList.value.reduce((acc, cur) => acc + (cur.jumlah_desa || 0), 0);
      const totRuas = filteredKecamatanList.value.reduce((acc, cur) => acc + (cur.jumlah_ruas || 0), 0);
      const totPanjang = filteredKecamatanList.value.reduce((acc, cur) => acc + (cur.total_panjang || 0), 0);
      const totTerbangun = filteredKecamatanList.value.reduce((acc, cur) => acc + (cur.panjang_dibangun || 0), 0);
      const totBelum = filteredKecamatanList.value.reduce((acc, cur) => acc + (cur.panjang_belum || 0), 0);
      const totProgress = totPanjang > 0 ? (totTerbangun / totPanjang) * 100 : 0;

      const kecSummaryRow = [
        '',
        '',
        'TOTAL KABUPATEN BOJONEGORO',
        totDesa,
        totRuas,
        Math.round(totPanjang * 100) / 100,
        Math.round(totTerbangun * 100) / 100,
        Math.round(totBelum * 100) / 100,
        Math.round((totPanjang / 1000) * 100) / 100,
        Math.round((totTerbangun / 1000) * 100) / 100,
        Math.round((totBelum / 1000) * 100) / 100,
        Number(totProgress.toFixed(2)),
      ];

      const kecSheetAoa = [
        ['PEMERINTAH KABUPATEN BOJONEGORO'],
        ['BADAN PERENCANAAN PEMBANGUNAN DAERAH'],
        [`REKAPITULASI TINGKAT KECAMATAN - ${kategoriLabel.toUpperCase()}`],
        [filterInfo],
        [],
        kecHeaders,
        ...kecDataRows,
        kecSummaryRow,
      ];

      const wsKec = XLSX.utils.aoa_to_sheet(kecSheetAoa);
      wsKec['!cols'] = autoFitColumns(kecSheetAoa);
      XLSX.utils.book_append_sheet(wb, wsKec, 'Rekap Kecamatan');
    }

    // ─── Sheet 2: Rekap Desa ───────────────────────────────────────────────
    if (scope === 'all' || scope === 'desa') {
      const filterParams: RekapFilterParams = {};
      if (selectedTahun.value !== 'semua') filterParams.tahun = Number(selectedTahun.value);
      if (selectedKondisi.value !== 'semua') filterParams.kondisi = selectedKondisi.value;
      if (selectedSumberDana.value !== 'semua') filterParams.sumber_dana = selectedSumberDana.value;

      const allDesaList = await api.fetchRekapDesa(null, activeKategori.value, filterParams);

      const desaHeaders = [
        'No',
        'Kecamatan',
        'Kode Desa',
        'Nama Desa',
        'Jumlah Ruas / Segmen',
        'Total Panjang Master (m)',
        'Panjang Terbangun (m)',
        'Panjang Belum Terbangun (m)',
        'Panjang Master (km)',
        'Panjang Terbangun (km)',
        'Panjang Belum (km)',
        'Progress Capaian (%)',
      ];

      const desaDataRows = (allDesaList || []).map((desa, idx) => {
        const totalP = Number(desa.total_panjang || 0);
        const pTerbangun = Number(desa.panjang_dibangun || 0);
        const pBelum = Number(desa.panjang_belum || 0);
        return [
          idx + 1,
          desa.kecamatan || '-',
          desa.id_desa,
          desa.nama_desa,
          desa.jumlah_ruas,
          Math.round(totalP * 100) / 100,
          Math.round(pTerbangun * 100) / 100,
          Math.round(pBelum * 100) / 100,
          Math.round((totalP / 1000) * 100) / 100,
          Math.round((pTerbangun / 1000) * 100) / 100,
          Math.round((pBelum / 1000) * 100) / 100,
          Number((desa.progress || 0).toFixed(2)),
        ];
      });

      const dTotRuas = (allDesaList || []).reduce((acc, cur) => acc + (cur.jumlah_ruas || 0), 0);
      const dTotPanjang = (allDesaList || []).reduce((acc, cur) => acc + (cur.total_panjang || 0), 0);
      const dTotTerbangun = (allDesaList || []).reduce((acc, cur) => acc + (cur.panjang_dibangun || 0), 0);
      const dTotBelum = (allDesaList || []).reduce((acc, cur) => acc + (cur.panjang_belum || 0), 0);
      const dTotProgress = dTotPanjang > 0 ? (dTotTerbangun / dTotPanjang) * 100 : 0;

      const desaSummaryRow = [
        '',
        '',
        '',
        'TOTAL SELURUH DESA',
        dTotRuas,
        Math.round(dTotPanjang * 100) / 100,
        Math.round(dTotTerbangun * 100) / 100,
        Math.round(dTotBelum * 100) / 100,
        Math.round((dTotPanjang / 1000) * 100) / 100,
        Math.round((dTotTerbangun / 1000) * 100) / 100,
        Math.round((dTotBelum / 1000) * 100) / 100,
        Number(dTotProgress.toFixed(2)),
      ];

      const desaSheetAoa = [
        ['PEMERINTAH KABUPATEN BOJONEGORO'],
        ['BADAN PERENCANAAN PEMBANGUNAN DAERAH'],
        [`RINCIAN REKAPITULASI TINGKAT DESA - ${kategoriLabel.toUpperCase()}`],
        [filterInfo],
        [],
        desaHeaders,
        ...desaDataRows,
        desaSummaryRow,
      ];

      const wsDesa = XLSX.utils.aoa_to_sheet(desaSheetAoa);
      wsDesa['!cols'] = autoFitColumns(desaSheetAoa);
      XLSX.utils.book_append_sheet(wb, wsDesa, 'Rekap Desa');
    }

    // ─── Sheet 3: Rincian Ruas Aktif (Jika ada ruas yang dimuat di state) ───
    const cachedRuasEntries = Object.entries(ruasDataMap.value);
    if (cachedRuasEntries.length > 0 && scope === 'all') {
      const ruasHeaders = [
        'No',
        'Kecamatan',
        'Desa',
        'Kode Ruas',
        'Nama Ruas / Keterangan',
        'Panjang Master (m)',
        'Panjang Terbangun (m)',
        'Panjang Sisa (m)',
        'Progress (%)',
        'Jumlah Segmen',
        'Kondisi',
        'Sumber Dana',
        'Tahun Pembangunan',
        'Status Aset',
        'Status Verifikasi',
      ];

      const ruasDataRows: any[][] = [];
      let rIdx = 1;

      for (const [idDesa, ruasList] of cachedRuasEntries) {
        let desaName = idDesa;
        let kecName = '-';
        for (const [, dList] of Object.entries(desaDataMap.value)) {
          const matchDesa = dList.find((d) => String(d.id_desa) === String(idDesa));
          if (matchDesa) {
            desaName = matchDesa.nama_desa;
            kecName = matchDesa.kecamatan;
            break;
          }
        }

        for (const r of ruasList) {
          ruasDataRows.push([
            rIdx++,
            kecName,
            desaName,
            r.kode_ruas || '-',
            r.nama_ruas,
            r.panjang_master || r.panjang || 0,
            r.panjang_dibangun || 0,
            r.panjang_tersisa || 0,
            r.progress || 0,
            r.jumlah_segmen || 0,
            r.kondisi || '-',
            r.sumber_dana || '-',
            r.tahun_pembangunan || '-',
            r.status_aset || '-',
            r.status_verifikasi || '-',
          ]);
        }
      }

      if (ruasDataRows.length > 0) {
        const ruasSheetAoa = [
          ['PEMERINTAH KABUPATEN BOJONEGORO'],
          ['BADAN PERENCANAAN PEMBANGUNAN DAERAH'],
          [`DATA RINCIAN RUAS JALAN YANG DIMUAT - ${kategoriLabel.toUpperCase()}`],
          [filterInfo],
          [],
          ruasHeaders,
          ...ruasDataRows,
        ];

        const wsRuas = XLSX.utils.aoa_to_sheet(ruasSheetAoa);
        wsRuas['!cols'] = autoFitColumns(ruasSheetAoa);
        XLSX.utils.book_append_sheet(wb, wsRuas, 'Rincian Ruas Aktif');
      }
    }

    // Generate filename
    const dateFile = new Date().toISOString().slice(0, 10);
    const scopeLabel = scope === 'kecamatan' ? 'Kecamatan' : scope === 'desa' ? 'Desa' : 'Lengkap';
    const fileName = `Rekap_Jalan_${activeKategori.value}_${scopeLabel}_Bojonegoro_${dateFile}.xlsx`;

    // Download file
    XLSX.writeFile(wb, fileName);

    toast.add({
      icon: 'i-lucide-check-circle',
      title: 'Ekspor Excel Berhasil',
      description: `Berkas ${fileName} berhasil diunduh.`,
      color: 'success',
    });
  } catch (err: any) {
    console.error('Gagal mengekspor data ke Excel:', err);
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Ekspor Excel',
      description: err?.message || 'Terjadi kesalahan saat memproses data Excel.',
      color: 'error',
    });
  } finally {
    exportingExcel.value = false;
  }
}

function exportCsv() {
  const items = filteredKecamatanList.value;
  if (items.length === 0) return;

  const headers = [
    'Kode Kecamatan',
    'Nama Kecamatan',
    'Jumlah Desa',
    'Jumlah Ruas/Segmen',
    'Total Panjang (m)',
    'Panjang Terbangun (m)',
    'Panjang Belum (m)',
    'Progres (%)',
  ];

  const rows = items.map((i) => [
    i.id_kecamatan,
    `"${i.nama_kecamatan}"`,
    i.jumlah_desa,
    i.jumlah_ruas,
    i.total_panjang,
    i.panjang_dibangun,
    i.panjang_belum,
    i.progress,
  ]);

  const csvContent = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  link.setAttribute(
    'download',
    `rekap_jalan_${activeKategori.value}_${new Date().toISOString().slice(0, 10)}.csv`
  );
  link.click();
  URL.revokeObjectURL(url);
}

const exportMenuItems = computed(() => [
  [
    {
      label: 'Ekspor Excel Lengkap (.xlsx)',
      icon: 'i-lucide-file-spreadsheet',
      description: 'Multi-Sheet: Rekap Kecamatan & Rincian Seluruh Desa',
      onSelect: () => handleExportExcel('all'),
    },
    {
      label: 'Ekspor Rekap Kecamatan (.xlsx)',
      icon: 'i-lucide-building-2',
      description: 'Hanya sheet ringkasan tingkat kecamatan',
      onSelect: () => handleExportExcel('kecamatan'),
    },
    {
      label: 'Ekspor Rekap Seluruh Desa (.xlsx)',
      icon: 'i-lucide-map-pin',
      description: 'Hanya sheet rincian seluruh desa Bojonegoro',
      onSelect: () => handleExportExcel('desa'),
    },
  ],
  [
    {
      label: 'Ekspor CSV Ringkas (.csv)',
      icon: 'i-lucide-file-text',
      description: 'Format teks Comma-Separated sederhana',
      onSelect: () => exportCsv(),
    },
  ],
]);

// Watchers
watch(
  [activeKategori, selectedTahun, selectedKondisi, selectedSumberDana],
  () => {
    loadKecamatan();
  }
);

onMounted(() => {
  loadKecamatan();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <div class="size-8 rounded-lg bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
            <UIcon name="i-lucide-route" class="size-5" />
          </div>
          <h1 class="text-xl font-bold text-gray-900 dark:text-white">
            Rekapitulasi Jalan Poros Desa & Non-Ruas
          </h1>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 ms-10">
          Monitoring capaian panjang, kondisi, dan progres fisik jalan poros desa (Dalam Database) serta segmen mandiri/non-ruas (Diluar Database) se-Kabupaten Bojonegoro.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <UDropdownMenu
          :items="exportMenuItems"
          :content="{ align: 'end', side: 'bottom' }"
        >
          <UButton
            color="primary"
            variant="solid"
            icon="i-lucide-file-spreadsheet"
            trailing-icon="i-lucide-chevron-down"
            size="sm"
            :loading="exportingExcel"
            label="Ekspor Excel"
          />
        </UDropdownMenu>

        <UButton
          color="neutral"
          variant="subtle"
          icon="i-lucide-refresh-cw"
          size="sm"
          :loading="loadingKecamatan"
          label="Muat Ulang"
          @click="loadKecamatan"
        />
      </div>
    </div>

    <!-- Tab Switcher (Ruas vs Non-Ruas) -->
    <div class="flex items-center border-b border-gray-200/70 dark:border-white/[0.08] gap-4">
      <button
        type="button"
        :class="[
          'pb-3 pt-1 text-sm font-semibold flex items-center gap-2 border-b-2 transition-colors cursor-pointer',
          activeKategori === 'ruas'
            ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400'
            : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200',
        ]"
        @click="activeKategori = 'ruas'"
      >
        <UIcon name="i-lucide-route" class="size-4" />
        <span>Ruas Poros Desa</span>
        <span class="text-xs px-2 py-0.5 rounded-full bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/40">
          Dalam Database
        </span>
      </button>

      <button
        type="button"
        :class="[
          'pb-3 pt-1 text-sm font-semibold flex items-center gap-2 border-b-2 transition-colors cursor-pointer',
          activeKategori === 'non_ruas'
            ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400'
            : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200',
        ]"
        @click="activeKategori = 'non_ruas'"
      >
        <UIcon name="i-lucide-git-commit" class="size-4" />
        <span>Jalan Non-Ruas</span>
        <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/40">
          Diluar Database
        </span>
      </button>
    </div>

    <!-- Metrik Statistik Kartu (Summary Cards) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="p-4 rounded-xl border border-gray-200/70 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] shadow-xs">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Panjang</span>
          <UIcon name="i-lucide-ruler" class="size-4 text-gray-400" />
        </div>
        <div class="text-xl font-bold font-mono text-gray-900 dark:text-white mt-1">
          {{ formatMeterOrKm(summaryTotals.totalPanjangMeter) }}
        </div>
        <p class="text-[11px] text-gray-400 mt-1">
          Dari <span class="font-semibold text-gray-700 dark:text-gray-300">{{ summaryTotals.totalKecamatan }}</span> kecamatan & <span class="font-semibold text-gray-700 dark:text-gray-300">{{ formatNumber(summaryTotals.totalRuas) }}</span> {{ activeKategori === 'ruas' ? 'ruas' : 'segmen' }}
        </p>
      </div>

      <div class="p-4 rounded-xl border border-emerald-200/70 dark:border-emerald-900/40 bg-emerald-50/20 dark:bg-emerald-950/15 shadow-xs">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-emerald-700 dark:text-emerald-400">Sudah Terbangun</span>
          <UIcon name="i-lucide-check-circle" class="size-4 text-emerald-500" />
        </div>
        <div class="text-xl font-bold font-mono text-emerald-700 dark:text-emerald-400 mt-1">
          {{ formatMeterOrKm(summaryTotals.totalPanjangDibangun) }}
        </div>
        <p class="text-[11px] text-emerald-600/80 dark:text-emerald-400/70 mt-1">
          Tercatat dalam {{ formatNumber(summaryTotals.totalSegmen) }} segmen fisik
        </p>
      </div>

      <div class="p-4 rounded-xl border border-orange-200/70 dark:border-orange-900/40 bg-orange-50/20 dark:bg-orange-950/15 shadow-xs">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-orange-700 dark:text-orange-400">Belum Terbangun</span>
          <UIcon name="i-lucide-clock" class="size-4 text-orange-500" />
        </div>
        <div class="text-xl font-bold font-mono text-orange-700 dark:text-orange-400 mt-1">
          {{ formatMeterOrKm(summaryTotals.totalPanjangBelum) }}
        </div>
        <p class="text-[11px] text-orange-600/80 dark:text-orange-400/70 mt-1">
          Sisa ruas jalan belum penanganan
        </p>
      </div>

      <div class="p-4 rounded-xl border border-blue-200/70 dark:border-blue-900/40 bg-blue-50/20 dark:bg-blue-950/15 shadow-xs">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-blue-700 dark:text-blue-400">Rata-rata Progres</span>
          <UIcon name="i-lucide-trending-up" class="size-4 text-blue-500" />
        </div>
        <div class="text-xl font-bold font-mono text-blue-700 dark:text-blue-400 mt-1">
          {{ summaryTotals.rataRataProgress.toFixed(1) }}%
        </div>
        <div class="w-full bg-gray-200 dark:bg-gray-800 h-1.5 rounded-full overflow-hidden mt-2">
          <div
            class="h-full bg-blue-600 dark:bg-blue-400 rounded-full transition-all duration-500"
            :style="{ width: `${summaryTotals.rataRataProgress}%` }"
          />
        </div>
      </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="p-4 rounded-xl border border-gray-200/70 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] shadow-xs space-y-3">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
        <!-- Search Input -->
        <div>
          <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Cari Kecamatan</label>
          <UInput
            v-model="searchQuery"
            icon="i-lucide-search"
            placeholder="Ketik nama kecamatan..."
            class="w-full"
            size="sm"
          />
        </div>

        <!-- Filter Tahun -->
        <div>
          <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Tahun Anggaran</label>
          <USelectMenu
            v-model="selectedTahun"
            :items="tahunOptions"
            value-key="value"
            label-key="label"
            class="w-full"
            size="sm"
          />
        </div>

        <!-- Filter Kondisi -->
        <div>
          <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Kondisi Jalan</label>
          <USelectMenu
            v-model="selectedKondisi"
            :items="kondisiOptions"
            value-key="value"
            label-key="label"
            class="w-full"
            size="sm"
          />
        </div>

        <!-- Filter Sumber Dana -->
        <div>
          <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Sumber Dana</label>
          <div class="flex items-center gap-2">
            <USelectMenu
              v-model="selectedSumberDana"
              :items="sumberDanaOptions"
              value-key="value"
              label-key="label"
              class="w-full"
              size="sm"
            />
            <UButton
              v-if="searchQuery || selectedTahun !== 'semua' || selectedKondisi !== 'semua' || selectedSumberDana !== 'semua'"
              color="neutral"
              variant="ghost"
              icon="i-lucide-x"
              size="sm"
              class="shrink-0"
              title="Reset Filter"
              @click="handleResetFilter"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Tabel Hirarki 4 Level -->
    <div class="rounded-xl border border-gray-200/70 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] overflow-hidden shadow-xs">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="border-b border-gray-200/70 dark:border-white/[0.08] bg-gray-50/80 dark:bg-white/[0.02] text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider text-[11px]">
              <th class="py-3 px-4 w-12 text-center">#</th>
              <th class="py-3 px-4 min-w-[220px]">Wilayah / Ruas Jalan</th>
              <th class="py-3 px-4 text-center min-w-[100px]">Jml Desa / Ruas</th>
              <th class="py-3 px-4 text-right min-w-[130px]">Total Panjang</th>
              <th class="py-3 px-4 text-right min-w-[130px]">Terbangun</th>
              <th class="py-3 px-4 text-right min-w-[130px]">Sisa Belum</th>
              <th class="py-3 px-4 min-w-[160px]">Progres Fisik</th>
              <th class="py-3 px-4 text-center w-28">Peta / Aksi</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
            <!-- Loading Skeleton -->
            <tr v-if="loadingKecamatan">
              <td colspan="8" class="py-12 text-center text-gray-500">
                <div class="flex flex-col items-center justify-center gap-2">
                  <UIcon name="i-lucide-loader-2" class="size-6 animate-spin text-blue-500" />
                  <span>Memuat data rekapitulasi jalan dari server...</span>
                </div>
              </td>
            </tr>

            <!-- Empty State -->
            <tr v-else-if="filteredKecamatanList.length === 0">
              <td colspan="8" class="py-12 text-center text-gray-500">
                <div class="flex flex-col items-center justify-center gap-2">
                  <UIcon name="i-lucide-database" class="size-8 text-gray-400" />
                  <span class="font-medium">Tidak ada data rekapitulasi ditemukan.</span>
                  <span class="text-[11px] text-gray-400">Silakan sesuaikan filter atau kata kunci pencarian Anda.</span>
                </div>
              </td>
            </tr>

            <!-- Level 1: Baris Kecamatan -->
            <template v-else v-for="(kec, idx) in filteredKecamatanList" :key="kec.id_kecamatan">
              <tr
                class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors cursor-pointer group"
                :class="{ 'bg-blue-50/30 dark:bg-blue-950/10': expandedKecamatan.has(kec.id_kecamatan) }"
                @click="toggleKecamatan(kec)"
              >
                <!-- Expand Chevron & Index -->
                <td class="py-3 px-4 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <button
                      type="button"
                      class="size-6 rounded flex items-center justify-center text-gray-400 group-hover:text-gray-700 dark:group-hover:text-gray-200 transition-transform"
                      :class="{ 'rotate-90 text-blue-600 dark:text-blue-400': expandedKecamatan.has(kec.id_kecamatan) }"
                    >
                      <UIcon name="i-lucide-chevron-right" class="size-4" />
                    </button>
                    <span class="font-mono text-gray-400 text-[11px]">{{ idx + 1 }}</span>
                  </div>
                </td>

                <!-- Nama Kecamatan -->
                <td class="py-3 px-4 font-semibold text-gray-900 dark:text-white">
                  <div class="flex items-center gap-2">
                    <UIcon name="i-lucide-map" class="size-4 text-blue-500 shrink-0" />
                    <span>Kecamatan {{ kec.nama_kecamatan }}</span>
                    <span class="text-[10px] font-mono text-gray-400 font-normal">({{ kec.id_kecamatan }})</span>
                  </div>
                </td>

                <!-- Jml Desa & Ruas -->
                <td class="py-3 px-4 text-center font-mono">
                  <span class="font-semibold text-gray-800 dark:text-gray-200">{{ kec.jumlah_desa }}</span> desa
                  <span class="text-gray-400 mx-1">/</span>
                  <span class="font-semibold text-gray-800 dark:text-gray-200">{{ kec.jumlah_ruas }}</span> ruas
                </td>

                <!-- Total Panjang -->
                <td class="py-3 px-4 text-right font-mono font-semibold text-gray-900 dark:text-white">
                  {{ formatMeterOrKm(kec.total_panjang) }}
                </td>

                <!-- Panjang Dibangun -->
                <td class="py-3 px-4 text-right font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                  {{ formatMeterOrKm(kec.panjang_dibangun) }}
                </td>

                <!-- Sisa Belum Dibangun -->
                <td class="py-3 px-4 text-right font-mono font-medium text-orange-600 dark:text-orange-400">
                  {{ formatMeterOrKm(kec.panjang_belum) }}
                </td>

                <!-- Progres Bar & Persen -->
                <td class="py-3 px-4">
                  <div class="flex items-center gap-2">
                    <div class="flex-1 bg-gray-100 dark:bg-gray-800 h-2 rounded-full overflow-hidden">
                      <div
                        class="h-full rounded-full transition-all duration-300"
                        :class="getProgressBarClass(kec.progress)"
                        :style="{ width: `${Math.min(Math.max(kec.progress, 0), 100)}%` }"
                      />
                    </div>
                    <span class="font-mono font-semibold text-[11px] w-12 text-right text-gray-800 dark:text-gray-200">
                      {{ Number(kec.progress || 0).toFixed(1) }}%
                    </span>
                  </div>
                </td>

                <!-- Aksi Expand -->
                <td class="py-3 px-4 text-center" @click.stop>
                  <UButton
                    size="xs"
                    color="neutral"
                    variant="ghost"
                    :label="expandedKecamatan.has(kec.id_kecamatan) ? 'Tutup' : 'Lihat Desa'"
                    :icon="expandedKecamatan.has(kec.id_kecamatan) ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
                    @click="toggleKecamatan(kec)"
                  />
                </td>
              </tr>

              <!-- ─── Level 2: Expanded Desa List ──────────────────────────── -->
              <template v-if="expandedKecamatan.has(kec.id_kecamatan)">
                <!-- Loading Desa -->
                <tr v-if="loadingDesaMap[kec.id_kecamatan]" class="bg-gray-50/40 dark:bg-white/[0.01]">
                  <td colspan="8" class="py-4 px-12 text-gray-500">
                    <div class="flex items-center gap-2">
                      <UIcon name="i-lucide-loader-2" class="size-4 animate-spin text-blue-500" />
                      <span>Memuat daftar desa di Kecamatan {{ kec.nama_kecamatan }}...</span>
                    </div>
                  </td>
                </tr>

                <!-- Desa Empty -->
                <tr
                  v-else-if="!desaDataMap[kec.id_kecamatan] || desaDataMap[kec.id_kecamatan].length === 0"
                  class="bg-gray-50/40 dark:bg-white/[0.01]"
                >
                  <td colspan="8" class="py-4 px-12 text-gray-400 italic">
                    Belum ada data desa untuk kecamatan ini.
                  </td>
                </tr>

                <!-- Desa Rows -->
                <template v-else v-for="(desa, dIdx) in desaDataMap[kec.id_kecamatan]" :key="desa.id_desa">
                  <tr
                    class="bg-gray-50/40 dark:bg-[#080d16] hover:bg-gray-100/60 dark:hover:bg-white/[0.03] transition-colors cursor-pointer"
                    :class="{ 'bg-emerald-50/20 dark:bg-emerald-950/10': expandedDesa.has(desa.id_desa) }"
                    @click="toggleDesa(desa)"
                  >
                    <!-- Indent & Expand -->
                    <td class="py-2.5 px-4 text-center">
                      <div class="flex items-center justify-end pe-2">
                        <button
                          type="button"
                          class="size-5 rounded flex items-center justify-center text-gray-400 transition-transform"
                          :class="{ 'rotate-90 text-emerald-600 dark:text-emerald-400': expandedDesa.has(desa.id_desa) }"
                        >
                          <UIcon name="i-lucide-chevron-right" class="size-3.5" />
                        </button>
                      </div>
                    </td>

                    <!-- Nama Desa -->
                    <td class="py-2.5 px-4 font-medium text-gray-800 dark:text-gray-200">
                      <div class="flex items-center gap-2 ps-3 border-s-2 border-blue-400 dark:border-blue-500">
                        <UIcon name="i-lucide-home" class="size-3.5 text-gray-400" />
                        <span>Desa {{ desa.nama_desa }}</span>
                        <span class="text-[10px] font-mono text-gray-400">({{ desa.id_desa }})</span>
                      </div>
                    </td>

                    <!-- Jml Ruas -->
                    <td class="py-2.5 px-4 text-center font-mono text-gray-600 dark:text-gray-300">
                      {{ desa.jumlah_ruas }} ruas
                    </td>

                    <!-- Total Panjang Desa -->
                    <td class="py-2.5 px-4 text-right font-mono text-gray-800 dark:text-gray-200">
                      {{ formatMeterOrKm(desa.total_panjang) }}
                    </td>

                    <!-- Panjang Dibangun -->
                    <td class="py-2.5 px-4 text-right font-mono text-emerald-600 dark:text-emerald-400 font-medium">
                      {{ formatMeterOrKm(desa.panjang_dibangun) }}
                    </td>

                    <!-- Panjang Belum -->
                    <td class="py-2.5 px-4 text-right font-mono text-orange-600 dark:text-orange-400">
                      {{ formatMeterOrKm(desa.panjang_belum) }}
                    </td>

                    <!-- Progres Bar Desa -->
                    <td class="py-2.5 px-4">
                      <div class="flex items-center gap-2">
                        <div class="flex-1 bg-gray-200 dark:bg-gray-800 h-1.5 rounded-full overflow-hidden">
                          <div
                            class="h-full rounded-full"
                            :class="getProgressBarClass(desa.progress)"
                            :style="{ width: `${Math.min(Math.max(desa.progress, 0), 100)}%` }"
                          />
                        </div>
                        <span class="font-mono text-[11px] w-12 text-right text-gray-600 dark:text-gray-300">
                          {{ Number(desa.progress || 0).toFixed(1) }}%
                        </span>
                      </div>
                    </td>

                    <!-- Aksi Desa -->
                    <td class="py-2.5 px-4 text-center" @click.stop>
                      <UButton
                        size="xs"
                        color="neutral"
                        variant="ghost"
                        :label="expandedDesa.has(desa.id_desa) ? 'Tutup' : 'Lihat Ruas'"
                        :icon="expandedDesa.has(desa.id_desa) ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
                        @click="toggleDesa(desa)"
                      />
                    </td>
                  </tr>

                  <!-- ─── Level 3: Expanded Ruas / Segmen List ──────────────── -->
                  <template v-if="expandedDesa.has(desa.id_desa)">
                    <!-- Loading Ruas -->
                    <tr v-if="loadingRuasMap[desa.id_desa]" class="bg-gray-100/30 dark:bg-black/20">
                      <td colspan="8" class="py-3 px-16 text-gray-500">
                        <div class="flex items-center gap-2">
                          <UIcon name="i-lucide-loader-2" class="size-4 animate-spin text-emerald-500" />
                          <span>Memuat daftar ruas di Desa {{ desa.nama_desa }}...</span>
                        </div>
                      </td>
                    </tr>

                    <!-- Ruas Empty -->
                    <tr
                      v-else-if="!ruasDataMap[desa.id_desa] || ruasDataMap[desa.id_desa].length === 0"
                      class="bg-gray-100/30 dark:bg-black/20"
                    >
                      <td colspan="8" class="py-3 px-16 text-gray-400 italic">
                        Tidak ada data ruas/segmen tercatat untuk desa ini.
                      </td>
                    </tr>

                    <!-- Ruas Rows -->
                    <template v-else v-for="ruas in ruasDataMap[desa.id_desa]" :key="ruas.id">
                      <tr
                        class="bg-white/80 dark:bg-[#070b12] hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors"
                        :class="{ 'bg-amber-50/15 dark:bg-amber-950/10': expandedRuas.has(ruas.id) }"
                      >
                        <!-- Ruas Level Icon & Expand Child -->
                        <td class="py-2.5 px-4 text-center">
                          <div class="flex items-center justify-end">
                            <button
                              v-if="ruas.jumlah_segmen > 0"
                              type="button"
                              class="size-5 rounded flex items-center justify-center text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-transform"
                              :class="{ 'rotate-90 text-blue-500': expandedRuas.has(ruas.id) }"
                              title="Tampilkan rincian segmen fisik"
                              @click="toggleRuas(ruas)"
                            >
                              <UIcon name="i-lucide-chevron-right" class="size-3" />
                            </button>
                            <span v-else class="size-2 rounded-full bg-gray-300 dark:bg-gray-700 me-1" />
                          </div>
                        </td>

                        <!-- Nama Ruas / Info -->
                        <td class="py-2.5 px-4">
                          <div class="ps-6 border-s-2 border-emerald-400 dark:border-emerald-500 space-y-1">
                            <div class="flex items-center gap-1.5 flex-wrap">
                              <!-- Badge Poros vs Non-Ruas -->
                              <span
                                v-if="ruas.is_poros !== false && ruas.kode_ruas && ruas.kode_ruas !== '-'"
                                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-medium bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/40"
                              >
                                Ruas {{ ruas.kode_ruas }}
                              </span>
                              <span
                                v-else
                                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-800/40"
                              >
                                Segmen Mandiri
                              </span>

                              <span
                                v-if="ruas.atribut?.jenis_perkerasan"
                                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300"
                              >
                                {{ ruas.atribut.jenis_perkerasan }}
                              </span>

                              <span
                                v-if="ruas.kondisi"
                                :class="['inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium capitalize', getKondisiBadgeClass(ruas.kondisi)]"
                              >
                                {{ ruas.kondisi }}
                              </span>
                            </div>

                            <p class="font-medium text-gray-900 dark:text-white text-xs">
                              {{ ruas.nama_ruas }}
                            </p>
                          </div>
                        </td>

                        <!-- Jml Segmen Fisik -->
                        <td class="py-2.5 px-4 text-center font-mono text-gray-600 dark:text-gray-400">
                          <span
                            v-if="ruas.jumlah_segmen > 0"
                            class="cursor-pointer hover:underline text-blue-600 dark:text-blue-400 font-medium"
                            @click="toggleRuas(ruas)"
                          >
                            {{ ruas.jumlah_segmen }} segmen
                          </span>
                          <span v-else class="text-gray-400">-</span>
                        </td>

                        <!-- Panjang Master -->
                        <td class="py-2.5 px-4 text-right font-mono text-gray-900 dark:text-white">
                          {{ formatMeterOrKm(ruas.panjang_master || ruas.panjang) }}
                        </td>

                        <!-- Terbangun -->
                        <td class="py-2.5 px-4 text-right font-mono text-emerald-600 dark:text-emerald-400">
                          {{ formatMeterOrKm(ruas.panjang_dibangun) }}
                        </td>

                        <!-- Sisa -->
                        <td class="py-2.5 px-4 text-right font-mono text-orange-600 dark:text-orange-400">
                          {{ formatMeterOrKm(ruas.panjang_tersisa) }}
                        </td>

                        <!-- Progres Fisik -->
                        <td class="py-2.5 px-4">
                          <div class="flex items-center gap-2">
                            <div class="flex-1 bg-gray-100 dark:bg-gray-800 h-1.5 rounded-full overflow-hidden">
                              <div
                                class="h-full rounded-full"
                                :class="getProgressBarClass(ruas.progress)"
                                :style="{ width: `${Math.min(Math.max(ruas.progress, 0), 100)}%` }"
                              />
                            </div>
                            <span class="font-mono text-[11px] w-12 text-right text-gray-700 dark:text-gray-300">
                              {{ Number(ruas.progress || 0).toFixed(1) }}%
                            </span>
                          </div>
                        </td>

                        <!-- Tombol Aksi Preview Peta -->
                        <td class="py-2.5 px-4 text-center">
                          <div class="flex items-center justify-center gap-1">
                            <UButton
                              size="xs"
                              color="primary"
                              variant="soft"
                              icon="i-lucide-map-pin"
                              title="Tampilkan peta spasial ruas ini"
                              @click="openPreviewMap(ruas, { desa: desa.nama_desa, kecamatan: kec.nama_kecamatan })"
                            />

                            <UButton
                              v-if="ruas.jumlah_segmen > 0"
                              size="xs"
                              color="neutral"
                              variant="ghost"
                              :icon="expandedRuas.has(ruas.id) ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
                              title="Rincian segmen pembangunan"
                              @click="toggleRuas(ruas)"
                            />
                          </div>
                        </td>
                      </tr>

                      <!-- ─── Level 4: Segmen Fisik Hasil Pembangunan ──────────── -->
                      <template v-if="expandedRuas.has(ruas.id)">
                        <!-- Loading Segmen -->
                        <tr v-if="loadingSegmenMap[ruas.id]" class="bg-amber-50/20 dark:bg-white/[0.01]">
                          <td colspan="8" class="py-2.5 px-20 text-gray-500">
                            <div class="flex items-center gap-2">
                              <UIcon name="i-lucide-loader-2" class="size-3.5 animate-spin text-amber-500" />
                              <span class="text-xs">Mengambil rincian segmen fisik...</span>
                            </div>
                          </td>
                        </tr>

                        <!-- Segmen Empty -->
                        <tr
                          v-else-if="!segmenDataMap[ruas.id] || segmenDataMap[ruas.id].length === 0"
                          class="bg-amber-50/20 dark:bg-white/[0.01]"
                        >
                          <td colspan="8" class="py-2 px-20 text-gray-400 italic text-[11px]">
                            Belum ada rincian segmen fisik tercatat untuk ruas ini.
                          </td>
                        </tr>

                        <!-- Segmen Fisik Rows -->
                        <template v-else v-for="(seg, sIdx) in segmenDataMap[ruas.id]" :key="seg.id">
                          <tr class="bg-gray-50/90 dark:bg-[#05080e] hover:bg-gray-100/70 dark:hover:bg-white/[0.03] transition-colors text-[11px]">
                            <td class="py-2 px-4 text-center text-gray-400 font-mono">
                              {{ sIdx + 1 }}
                            </td>

                            <!-- Info Segmen -->
                            <td class="py-2 px-4">
                              <div class="ps-9 border-s-2 border-amber-400 dark:border-amber-500 space-y-0.5">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                  <span class="font-mono text-[10px] text-gray-500">Segmen #{{ sIdx + 1 }}</span>
                                  <span v-if="seg.tahun" class="px-1.5 py-0.2 rounded bg-gray-200/70 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-mono text-[10px]">
                                    T.A. {{ seg.tahun }}
                                  </span>
                                  <span v-if="seg.sumber_dana" class="px-1.5 py-0.2 rounded bg-blue-100/80 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-medium text-[10px]">
                                    {{ seg.sumber_dana }}
                                  </span>
                                </div>
                                <p v-if="seg.keterangan" class="text-gray-600 dark:text-gray-400 line-clamp-1">
                                  {{ seg.keterangan }}
                                </p>
                              </div>
                            </td>

                            <!-- Lebar & Detail -->
                            <td class="py-2 px-4 text-center font-mono text-gray-600 dark:text-gray-300">
                              L: {{ seg.lebar }} m
                            </td>

                            <!-- Panjang Segmen -->
                            <td class="py-2 px-4 text-right font-mono font-medium text-gray-800 dark:text-gray-200">
                              {{ formatMeterOrKm(seg.panjang) }}
                            </td>

                            <!-- Status Terbangun -->
                            <td class="py-2 px-4 text-right font-mono text-emerald-600 dark:text-emerald-400">
                              {{ formatMeterOrKm(seg.panjang) }}
                            </td>

                            <!-- Sisa Belum -->
                            <td class="py-2 px-4 text-right font-mono text-gray-400">
                              -
                            </td>

                            <!-- Kondisi Fisik -->
                            <td class="py-2 px-4">
                              <span :class="['inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium capitalize', getKondisiBadgeClass(seg.kondisi)]">
                                {{ seg.kondisi }}
                              </span>
                            </td>

                            <!-- Aksi Peta Segmen -->
                            <td class="py-2 px-4 text-center">
                              <UButton
                                v-if="seg.geom"
                                size="xs"
                                color="neutral"
                                variant="ghost"
                                icon="i-lucide-map-pin"
                                title="Lihat segmen ini pada peta"
                                @click="openPreviewMap(seg, { desa: desa.nama_desa, kecamatan: kec.nama_kecamatan, nama_ruas: ruas.nama_ruas, kode_ruas: ruas.kode_ruas })"
                              />
                            </td>
                          </tr>
                        </template>
                      </template>
                    </template>
                  </template>
                </template>
              </template>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Preview Spasial Peta -->
    <ModalPreviewPeta
      v-model:open="previewModalOpen"
      :item="activePreviewItem"
    />
  </div>
</template>

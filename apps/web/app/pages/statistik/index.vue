<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import type {
  KategoriJalan,
  RekapKecamatanItem,
  RekapDesaItem,
  RekapRuasItem,
  RekapSegmenItem,
  RekapFilterParams,
  RekapSummaryCards,
} from '~/types/rekap-jalan'
import ModalPreviewPeta from '~/components/rekap-jalan/ModalPreviewPeta.vue'
import * as XLSX from 'xlsx'

useSeoMeta({
  title: 'Statistik & Analitik Infrastruktur Jalan | Melarosa Kabupaten Bojonegoro',
  description:
    'Observatorium statistik pembangunan jalan poros desa dan non-ruas Kabupaten Bojonegoro: grafik komparasi 28 kecamatan, distribusi klaster capaian, dan tabel hierarki 4 level hingga rincian segmen fisik.',
})

const toast = useToast()
const api = useRekapJalanApi()

// ─── Core Filter & Category State ───────────────────────────────────────────
const activeKategori = ref<KategoriJalan>('ruas')
const searchQuery = ref('')
const selectedTahun = ref<string>('semua')
const selectedKondisi = ref<string>('semua')
const selectedSumberDana = ref<string>('semua')
const selectedCluster = ref<'all' | 'sangat_tinggi' | 'tinggi' | 'menengah' | 'prioritas'>('all')
const sortBy = ref<'nama_asc' | 'nama_desc' | 'panjang_desc' | 'progress_desc' | 'belum_desc'>('nama_asc')
const chartDisplayLimit = ref<'top14' | 'all'>('all')

const loadingKecamatan = ref(false)
const errorKecamatan = ref<string | null>(null)
const kecamatanList = ref<RekapKecamatanItem[]>([])

// 4-Level Hierarchy Expansion Tracking
const expandedKecamatan = ref<Set<number>>(new Set())
const expandedDesa = ref<Set<string | number>>(new Set())
const expandedRuas = ref<Set<string | number>>(new Set())

// Lazy-loaded Caches for Level 2 (Desa), Level 3 (Ruas), Level 4 (Segmen)
const desaDataMap = ref<Record<number, RekapDesaItem[]>>({})
const loadingDesaMap = ref<Record<number, boolean>>({})

const ruasDataMap = ref<Record<string | number, RekapRuasItem[]>>({})
const loadingRuasMap = ref<Record<string | number, boolean>>({})

const segmenDataMap = ref<Record<string | number, RekapSegmenItem[]>>({})
const loadingSegmenMap = ref<Record<string | number, boolean>>({})

// Spatial Preview Modal State
const previewModalOpen = ref(false)
const activePreviewItem = ref<RekapRuasItem | RekapSegmenItem | null>(null)

// Filter Options
const tahunOptions = [
  { label: 'Semua Tahun Anggaran', value: 'semua' },
  { label: 'T.A. 2026', value: '2026' },
  { label: 'T.A. 2025', value: '2025' },
  { label: 'T.A. 2024', value: '2024' },
  { label: 'T.A. 2023', value: '2023' },
  { label: 'T.A. 2022', value: '2022' },
  { label: 'T.A. 2021', value: '2021' },
]

const kondisiOptions = [
  { label: 'Semua Kondisi Fisik', value: 'semua' },
  { label: 'Kondisi Baik', value: 'baik' },
  { label: 'Kondisi Sedang', value: 'sedang' },
  { label: 'Rusak Ringan', value: 'rusak ringan' },
  { label: 'Rusak Berat', value: 'rusak berat' },
]

const sumberDanaOptions = [
  { label: 'Semua Sumber Pendanaan', value: 'semua' },
  { label: 'Bantuan Keuangan Khusus (BKK)', value: 'BKK' },
  { label: 'APBD Kabupaten', value: 'APBD' },
  { label: 'Dana Desa (DD)', value: 'DD' },
]

// ─── Formatters (2 Desimal untuk km, Tanpa font-mono) ───────────────────────
function formatKm(meters?: number): string {
  const m = Number(meters || 0)
  if (!Number.isFinite(m) || m === 0) return '0,00 km'
  return `${(m / 1000).toLocaleString('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })} km`
}

function formatMeterOrKm(meters?: number): string {
  const m = Number(meters || 0)
  if (!Number.isFinite(m) || m === 0) return '0 m'
  if (Math.abs(m) >= 1000) {
    return `${(m / 1000).toLocaleString('id-ID', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })} km`
  }
  return `${Math.round(m).toLocaleString('id-ID')} m`
}

function formatMetersExact(meters?: number): string {
  const m = Number(meters || 0)
  if (!Number.isFinite(m)) return '0 m'
  return `${Math.round(m).toLocaleString('id-ID')} m`
}

function formatNumber(val?: number): string {
  const n = Number(val || 0)
  if (!Number.isFinite(n)) return '0'
  return n.toLocaleString('id-ID')
}

function formatPercent(val?: number): string {
  const n = Number(val || 0)
  if (!Number.isFinite(n)) return '0,00%'
  return `${n.toLocaleString('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}%`
}

function getKondisiBadgeClass(kondisi?: string): string {
  const k = (kondisi || '').toLowerCase()
  if (k.includes('rusak berat')) {
    return 'bg-rose-500/12 text-rose-700 dark:text-rose-300'
  }
  if (k.includes('rusak ringan') || k.includes('rusak')) {
    return 'bg-orange-500/12 text-orange-700 dark:text-orange-300'
  }
  if (k.includes('sedang')) {
    return 'bg-amber-500/12 text-amber-800 dark:text-amber-300'
  }
  if (k.includes('baik')) {
    return 'bg-emerald-500/12 text-emerald-800 dark:text-emerald-300'
  }
  return 'bg-slate-500/10 text-slate-700 dark:text-slate-300'
}

function getProgressBarClass(progress: number): string {
  if (progress >= 80) return 'bg-emerald-600 dark:bg-emerald-500'
  if (progress >= 60) return 'bg-teal-600 dark:bg-teal-400'
  if (progress >= 40) return 'bg-amber-500 dark:bg-amber-400'
  return 'bg-orange-600 dark:bg-orange-500'
}

function getClusterKey(progress: number): 'sangat_tinggi' | 'tinggi' | 'menengah' | 'prioritas' {
  if (progress >= 80) return 'sangat_tinggi'
  if (progress >= 60) return 'tinggi'
  if (progress >= 40) return 'menengah'
  return 'prioritas'
}

// ─── Computed Analytics & Filtering ─────────────────────────────────────────
const hasActiveFilter = computed(() =>
  Boolean(
    searchQuery.value.trim() ||
      selectedTahun.value !== 'semua' ||
      selectedKondisi.value !== 'semua' ||
      selectedSumberDana.value !== 'semua' ||
      selectedCluster.value !== 'all'
  )
)

const filteredKecamatanList = computed(() => {
  let list = [...kecamatanList.value]

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    list = list.filter((item) => item.nama_kecamatan.toLowerCase().includes(q))
  }

  if (selectedCluster.value !== 'all') {
    list = list.filter((item) => getClusterKey(Number(item.progress || 0)) === selectedCluster.value)
  }

  list.sort((a, b) => {
    if (sortBy.value === 'nama_asc') {
      return a.nama_kecamatan.localeCompare(b.nama_kecamatan, 'id', { numeric: true })
    }
    if (sortBy.value === 'nama_desc') {
      return b.nama_kecamatan.localeCompare(a.nama_kecamatan, 'id', { numeric: true })
    }
    if (sortBy.value === 'progress_desc') {
      return Number(b.progress || 0) - Number(a.progress || 0)
    }
    if (sortBy.value === 'belum_desc') {
      return Number(b.panjang_belum || 0) - Number(a.panjang_belum || 0)
    }
    return Number(b.total_panjang || 0) - Number(a.total_panjang || 0)
  })

  return list
})

// Global Summary Totals (from current filtered view)
const summaryTotals = computed<RekapSummaryCards>(() => {
  const items = filteredKecamatanList.value
  const totalKec = items.length
  let totalDesa = 0
  let totalRuas = 0
  let totalPanjangMeter = 0
  let totalPanjangDibangun = 0
  let totalPanjangBelum = 0
  let totalSegmen = 0

  for (const it of items) {
    totalDesa += Number(it.jumlah_desa || 0)
    totalRuas += Number(it.jumlah_ruas || 0)
    totalPanjangMeter += Number(it.total_panjang || 0)
    totalPanjangDibangun += Number(it.panjang_dibangun || 0)
    totalPanjangBelum += Number(it.panjang_belum || 0)
    totalSegmen += Number(it.jumlah_segmen || 0)
  }

  const rataRataProgress =
    totalPanjangMeter > 0 ? (totalPanjangDibangun / totalPanjangMeter) * 100 : 0

  return {
    totalKecamatan: totalKec,
    totalDesa,
    totalRuas,
    totalPanjangMeter,
    totalPanjangDibangun,
    totalPanjangBelum,
    rataRataProgress: Math.min(Math.max(rataRataProgress, 0), 100),
    totalSegmen,
  }
})

// Readiness Clusters across ALL loaded Kecamatan
const readinessClusters = computed(() => {
  const all = kecamatanList.value
  const total = all.length || 1
  const counts = {
    sangat_tinggi: 0,
    tinggi: 0,
    menengah: 0,
    prioritas: 0,
  }
  for (const item of all) {
    const key = getClusterKey(Number(item.progress || 0))
    counts[key] += 1
  }

  return [
    {
      key: 'sangat_tinggi' as const,
      label: 'Capaian Sangat Tinggi',
      range: '≥ 80%',
      count: counts.sangat_tinggi,
      share: Math.round((counts.sangat_tinggi / total) * 100),
      barClass: 'bg-emerald-600 dark:bg-emerald-500',
      badgeClass: 'bg-emerald-500/12 text-emerald-800 dark:text-emerald-300',
    },
    {
      key: 'tinggi' as const,
      label: 'Capaian Tinggi',
      range: '60% s/d < 80%',
      count: counts.tinggi,
      share: Math.round((counts.tinggi / total) * 100),
      barClass: 'bg-teal-600 dark:bg-teal-400',
      badgeClass: 'bg-teal-500/12 text-teal-800 dark:text-teal-300',
    },
    {
      key: 'menengah' as const,
      label: 'Capaian Menengah',
      range: '40% s/d < 60%',
      count: counts.menengah,
      share: Math.round((counts.menengah / total) * 100),
      barClass: 'bg-amber-500 dark:bg-amber-400',
      badgeClass: 'bg-amber-500/12 text-amber-800 dark:text-amber-300',
    },
    {
      key: 'prioritas' as const,
      label: 'Prioritas Percepatan',
      range: '< 40%',
      count: counts.prioritas,
      share: Math.round((counts.prioritas / total) * 100),
      barClass: 'bg-orange-600 dark:bg-orange-500',
      badgeClass: 'bg-orange-500/12 text-orange-800 dark:text-orange-300',
    },
  ]
})

// Chart 1 Data: Horizontal Stacked/Comparative Bar Chart per Kecamatan (Always sorted by Highest Total Km)
const chartSortedByPanjang = computed(() =>
  [...filteredKecamatanList.value].sort(
    (a, b) => Number(b.total_panjang || 0) - Number(a.total_panjang || 0)
  )
)

const maxKecamatanPanjang = computed(() => {
  const maxVal = Math.max(...chartSortedByPanjang.value.map((k) => Number(k.total_panjang || 0)), 1)
  return maxVal
})

const chartKecamatanItems = computed(() => {
  const list = chartSortedByPanjang.value
  if (chartDisplayLimit.value === 'top14') {
    return list.slice(0, 14)
  }
  return list
})

const longestNetworkKecamatan = computed(() => chartSortedByPanjang.value[0] || null)

const averagePanjangPerKecamatan = computed(() => {
  const count = filteredKecamatanList.value.length
  if (!count) return 0
  return summaryTotals.value.totalPanjangMeter / count
})

// Chart 3 Data: Top 5 Highest Progress vs Top 5 Largest Remaining Backlog (km)
const topProgressKecamatan = computed(() =>
  [...kecamatanList.value]
    .filter((k) => Number(k.total_panjang || 0) > 0)
    .sort((a, b) => Number(b.progress || 0) - Number(a.progress || 0))
    .slice(0, 5)
)

const topBacklogKecamatan = computed(() =>
  [...kecamatanList.value]
    .filter((k) => Number(k.panjang_belum || 0) > 0)
    .sort((a, b) => Number(b.panjang_belum || 0) - Number(a.panjang_belum || 0))
    .slice(0, 5)
)

// SVG Donut Chart Geometry
const donutCircumference = 2 * Math.PI * 42
const donutStrokeDashoffset = computed(() => {
  const pct = Math.min(Math.max(summaryTotals.value.rataRataProgress, 0), 100)
  return donutCircumference - (pct / 100) * donutCircumference
})

// ─── API Loaders ────────────────────────────────────────────────────────────
function buildFilterParams(): RekapFilterParams {
  const params: RekapFilterParams = {}
  if (selectedTahun.value !== 'semua') params.tahun = Number(selectedTahun.value)
  if (selectedKondisi.value !== 'semua') params.kondisi = selectedKondisi.value
  if (selectedSumberDana.value !== 'semua') params.sumber_dana = selectedSumberDana.value
  return params
}

async function loadKecamatan() {
  loadingKecamatan.value = true
  errorKecamatan.value = null
  expandedKecamatan.value.clear()
  expandedDesa.value.clear()
  expandedRuas.value.clear()
  desaDataMap.value = {}
  ruasDataMap.value = {}
  segmenDataMap.value = {}

  try {
    const params = buildFilterParams()
    kecamatanList.value = await api.fetchRekapKecamatan(activeKategori.value, params)
  } catch (err: any) {
    console.error('Gagal memuat rekap kecamatan:', err)
    errorKecamatan.value =
      err?.message || 'Tidak dapat mengambil data rekapitulasi jalan dari server.'
  } finally {
    loadingKecamatan.value = false
  }
}

async function toggleKecamatan(kec: RekapKecamatanItem) {
  const id = kec.id_kecamatan
  if (expandedKecamatan.value.has(id)) {
    expandedKecamatan.value.delete(id)
    return
  }

  expandedKecamatan.value.add(id)

  if (!desaDataMap.value[id]) {
    loadingDesaMap.value[id] = true
    try {
      const params = buildFilterParams()
      const res = await api.fetchRekapDesa(id, activeKategori.value, params)
      desaDataMap.value[id] = [...(res || [])].sort((a, b) =>
        (a.nama_desa || '').localeCompare(b.nama_desa || '', 'id', { numeric: true })
      )
    } catch (err: any) {
      toast.add({
        icon: 'i-lucide-alert-circle',
        title: `Gagal Memuat Desa (${kec.nama_kecamatan})`,
        description: err?.message || 'Gagal mengambil rincian desa.',
        color: 'error',
      })
    } finally {
      loadingDesaMap.value[id] = false
    }
  }
}

async function focusAndExpandKecamatan(kec: RekapKecamatanItem) {
  if (!expandedKecamatan.value.has(kec.id_kecamatan)) {
    await toggleKecamatan(kec)
  }
  if (import.meta.client) {
    const el = document.getElementById(`row-kec-${kec.id_kecamatan}`)
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
  }
}

async function toggleDesa(desa: RekapDesaItem) {
  const id = desa.id_desa
  if (expandedDesa.value.has(id)) {
    expandedDesa.value.delete(id)
    return
  }

  expandedDesa.value.add(id)

  if (!ruasDataMap.value[id]) {
    loadingRuasMap.value[id] = true
    try {
      const params = buildFilterParams()
      const res = await api.fetchRekapRuasByDesa(id, activeKategori.value, params)
      ruasDataMap.value[id] = [...(res || [])].sort((a, b) =>
        (a.nama_ruas || '').localeCompare(b.nama_ruas || '', 'id', { numeric: true })
      )
    } catch (err: any) {
      toast.add({
        icon: 'i-lucide-alert-circle',
        title: `Gagal Memuat Ruas (${desa.nama_desa})`,
        description: err?.message || 'Gagal mengambil daftar ruas desa.',
        color: 'error',
      })
    } finally {
      loadingRuasMap.value[id] = false
    }
  }
}

async function toggleRuas(ruas: RekapRuasItem) {
  const id = ruas.id
  if (expandedRuas.value.has(id)) {
    expandedRuas.value.delete(id)
    return
  }

  expandedRuas.value.add(id)

  if (!segmenDataMap.value[id]) {
    loadingSegmenMap.value[id] = true
    try {
      segmenDataMap.value[id] = await api.fetchDetailSegmen(id)
    } catch (err: any) {
      toast.add({
        icon: 'i-lucide-alert-circle',
        title: 'Gagal Memuat Segmen Fisik',
        description: err?.message || 'Gagal mengambil rincian segmen pembangunan.',
        color: 'error',
      })
    } finally {
      loadingSegmenMap.value[id] = false
    }
  }
}

function collapseAllRows() {
  expandedKecamatan.value.clear()
  expandedDesa.value.clear()
  expandedRuas.value.clear()
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
  } as any
  previewModalOpen.value = true
}

function handleResetFilter() {
  searchQuery.value = ''
  selectedTahun.value = 'semua'
  selectedKondisi.value = 'semua'
  selectedSumberDana.value = 'semua'
  selectedCluster.value = 'all'
  loadKecamatan()
}

// ─── Excel (.xlsx) & CSV Export ─────────────────────────────────────────────
const exportingExcel = ref(false)

function autoFitColumns(rows: any[][]) {
  const colWidths: { wch: number }[] = []
  rows.forEach((row) => {
    row.forEach((cell, colIdx) => {
      const len = cell !== null && cell !== undefined ? String(cell).length : 0
      if (!colWidths[colIdx] || len > colWidths[colIdx].wch) {
        colWidths[colIdx] = { wch: Math.min(65, Math.max(10, len + 3)) }
      }
    })
  })
  return colWidths
}

async function handleExportExcel(scope: 'all' | 'kecamatan' = 'all') {
  if (filteredKecamatanList.value.length === 0) return

  exportingExcel.value = true
  try {
    const wb = XLSX.utils.book_new()
    const kategoriLabel =
      activeKategori.value === 'ruas'
        ? 'Jalan Poros Desa (Dalam Database)'
        : 'Jalan Desa Non-Ruas (Diluar Database)'
    const dateStr = new Date().toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    })
    const filterInfo = `Kategori: ${kategoriLabel} | Tahun: ${selectedTahun.value} | Kondisi: ${selectedKondisi.value} | Sumber Dana: ${selectedSumberDana.value} | Tanggal Ekspor: ${dateStr}`

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
    ]

    const kecDataRows = filteredKecamatanList.value.map((item, idx) => {
      const totalP = Number(item.total_panjang || 0)
      const pTerbangun = Number(item.panjang_dibangun || 0)
      const pBelum = Number(item.panjang_belum || 0)
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
        Number(Number(item.progress || 0).toFixed(2)),
      ]
    })

    const kecSheetRows = [
      ['REKAPITULASI STATISTIK JALAN POROS DESA & NON-RUAS KABUPATEN BOJONEGORO'],
      [filterInfo],
      [],
      kecHeaders,
      ...kecDataRows,
    ]

    const wsKec = XLSX.utils.aoa_to_sheet(kecSheetRows)
    wsKec['!cols'] = autoFitColumns([kecHeaders, ...kecDataRows])
    XLSX.utils.book_append_sheet(wb, wsKec, 'Rekap Kecamatan')

    if (scope === 'all') {
      const params = buildFilterParams()
      const allDesaList = await api.fetchRekapDesa(null, activeKategori.value, params)
      if (allDesaList && allDesaList.length > 0) {
        const desaHeaders = [
          'No',
          'Kode Desa',
          'Nama Desa / Kelurahan',
          'Nama Kecamatan',
          'Jumlah Ruas',
          'Total Panjang (m)',
          'Panjang Terbangun (m)',
          'Panjang Belum (m)',
          'Total Panjang (km)',
          'Panjang Terbangun (km)',
          'Panjang Belum (km)',
          'Progres (%)',
        ]
        const desaRows = allDesaList.map((d, i) => [
          i + 1,
          d.id_desa,
          d.nama_desa,
          d.kecamatan,
          d.jumlah_ruas,
          Math.round(Number(d.total_panjang || 0) * 100) / 100,
          Math.round(Number(d.panjang_dibangun || 0) * 100) / 100,
          Math.round(Number(d.panjang_belum || 0) * 100) / 100,
          Math.round((Number(d.total_panjang || 0) / 1000) * 100) / 100,
          Math.round((Number(d.panjang_dibangun || 0) / 1000) * 100) / 100,
          Math.round((Number(d.panjang_belum || 0) / 1000) * 100) / 100,
          Number(Number(d.progress || 0).toFixed(2)),
        ])
        const wsDesa = XLSX.utils.aoa_to_sheet([
          ['RINCIAN STATISTIK JALAN TINGKAT DESA SE-KABUPATEN BOJONEGORO'],
          [filterInfo],
          [],
          desaHeaders,
          ...desaRows,
        ])
        wsDesa['!cols'] = autoFitColumns([desaHeaders, ...desaRows])
        XLSX.utils.book_append_sheet(wb, wsDesa, 'Rincian Seluruh Desa')
      }
    }

    const fileName = `statistik_jalan_bojonegoro_${activeKategori.value}_${new Date().toISOString().slice(0, 10)}.xlsx`
    XLSX.writeFile(wb, fileName)
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Mengekspor Berkas Excel',
      description: err?.message || 'Terjadi kendala saat menyiapkan berkas unduhan.',
      color: 'error',
    })
  } finally {
    exportingExcel.value = false
  }
}

function handleExportCsv() {
  const items = filteredKecamatanList.value
  if (items.length === 0) return

  const headers = [
    'Kode Kecamatan',
    'Nama Kecamatan',
    'Jumlah Desa',
    'Jumlah Ruas/Segmen',
    'Total Panjang (m)',
    'Panjang Terbangun (m)',
    'Panjang Belum (m)',
    'Total Panjang (km)',
    'Panjang Terbangun (km)',
    'Panjang Belum (km)',
    'Progres (%)',
  ]

  const rows = items.map((i) => [
    i.id_kecamatan,
    `"${i.nama_kecamatan}"`,
    i.jumlah_desa,
    i.jumlah_ruas,
    i.total_panjang,
    i.panjang_dibangun,
    i.panjang_belum,
    (Number(i.total_panjang || 0) / 1000).toFixed(2),
    (Number(i.panjang_dibangun || 0) / 1000).toFixed(2),
    (Number(i.panjang_belum || 0) / 1000).toFixed(2),
    Number(i.progress || 0).toFixed(2),
  ])

  const csvContent = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n')
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.setAttribute('href', url)
  link.setAttribute(
    'download',
    `statistik_jalan_bojonegoro_${activeKategori.value}_${new Date().toISOString().slice(0, 10)}.csv`
  )
  link.click()
  URL.revokeObjectURL(url)
}

watch([activeKategori, selectedTahun, selectedKondisi, selectedSumberDana], () => {
  loadKecamatan()
})

onMounted(() => {
  loadKecamatan()
})
</script>

<template>
  <div class="min-h-screen bg-[#F7F8F5] dark:bg-[#070b14] text-slate-900 dark:text-slate-100 flex flex-col font-sans transition-colors duration-300">
    <main class="flex-1 pt-5 sm:pt-10 pb-16 sm:pb-24">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 sm:space-y-10">

        <!-- 1. Editorial Masthead & Kategori Infrastruktur Switcher -->
        <header class="pb-6 sm:pb-8 lg:pb-10 border-b border-[#E4EAE3] dark:border-white/[0.07]">
          <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="inline-flex items-center gap-2.5">
              <span class="w-2 h-2 rounded-full bg-emerald-600 dark:bg-emerald-400"></span>
              <span class="text-[11px] sm:text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700 dark:text-emerald-400">
                Data Infrastruktur Jalan · Kabupaten Bojonegoro
              </span>
            </div>

            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-[#EAEFE9] dark:bg-[#0c1522] text-[11px] sm:text-xs font-medium text-slate-700 dark:text-slate-300">
              <UIcon name="i-lucide-activity" class="w-3.5 h-3.5 text-emerald-700 dark:text-emerald-400" />
              <span>Data Terintegrasi Simpul Spasial Daerah</span>
            </div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-end">
            <div class="lg:col-span-7">
              <h1 class="text-[clamp(1.7rem,3.5vw,2.75rem)] font-bold text-slate-900 dark:text-white tracking-tight leading-[1.12] mb-3 sm:mb-4">
                Statistik &amp; Analitik Infrastruktur Jalan
              </h1>
              <p class="text-sm sm:text-base lg:text-lg text-slate-600 dark:text-slate-400 leading-relaxed max-w-3xl">
                Rekapitulasi menyeluruh capaian panjang jalan poros desa, realisasi fisik terbangun, distribusi kondisi perkerasan,
                serta penelusuran berjenjang dari tingkat kecamatan, desa, ruas jalan, hingga segmen fisik pembangunan.
              </p>
            </div>

            <!-- Action Controls: Export & Refresh (Reflows cleanly on mobile) -->
            <div class="lg:col-span-5 grid grid-cols-2 sm:flex sm:flex-wrap lg:justify-end items-center gap-2.5">
              <button
                type="button"
                :disabled="loadingKecamatan"
                class="min-h-[44px] inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-[#EAEFE9] dark:bg-[#0c1522] hover:bg-[#dfe6de] dark:hover:bg-[#132033] text-slate-800 dark:text-slate-200 text-xs font-semibold transition-colors cursor-pointer disabled:opacity-50"
                @click="loadKecamatan"
              >
                <UIcon
                  name="i-lucide-refresh-cw"
                  class="w-4 h-4 text-emerald-700 dark:text-emerald-400 shrink-0"
                  :class="{ 'animate-spin': loadingKecamatan }"
                />
                <span>Segarkan Data</span>
              </button>

              <button
                type="button"
                :disabled="exportingExcel || filteredKecamatanList.length === 0"
                class="min-h-[44px] inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-[#EAEFE9] dark:bg-[#0c1522] hover:bg-[#dfe6de] dark:hover:bg-[#132033] text-slate-800 dark:text-slate-200 text-xs font-semibold transition-colors cursor-pointer disabled:opacity-50"
                @click="handleExportCsv"
              >
                <UIcon name="i-lucide-file-text" class="w-4 h-4 text-slate-600 dark:text-slate-300 shrink-0" />
                <span>Unduh CSV</span>
              </button>

              <button
                type="button"
                :disabled="exportingExcel || filteredKecamatanList.length === 0"
                class="col-span-2 sm:col-span-1 min-h-[44px] inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500 text-white text-xs font-semibold transition-colors cursor-pointer disabled:opacity-50"
                @click="handleExportExcel('all')"
              >
                <UIcon
                  :name="exportingExcel ? 'i-lucide-loader-2' : 'i-lucide-file-spreadsheet'"
                  class="w-4 h-4 shrink-0"
                  :class="{ 'animate-spin': exportingExcel }"
                />
                <span>{{ exportingExcel ? 'Menyiapkan Excel...' : 'Unduh Laporan Excel (.xlsx)' }}</span>
              </button>
            </div>
          </div>

          <!-- Segmented Scope Switcher: Ruas Poros Desa vs Jalan Non-Ruas -->
          <div class="mt-6 sm:mt-8 flex flex-col sm:flex-row sm:items-center justify-between gap-3.5">
            <div
              class="grid grid-cols-1 sm:inline-flex sm:flex-wrap items-center gap-2 p-1.5 rounded-2xl bg-[#EAEFE9] dark:bg-[#0c1522]"
              role="tablist"
              aria-label="Kategori Infrastruktur Jalan"
            >
              <button
                type="button"
                role="tab"
                :aria-selected="activeKategori === 'ruas'"
                class="min-h-[44px] inline-flex items-center justify-between sm:justify-start gap-2.5 px-4 sm:px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all cursor-pointer"
                :class="
                  activeKategori === 'ruas'
                    ? 'bg-white dark:bg-emerald-600 text-slate-900 dark:text-white shadow-xs'
                    : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                "
                @click="activeKategori = 'ruas'"
              >
                <span class="inline-flex items-center gap-2">
                  <UIcon name="i-lucide-route" class="w-4 h-4 shrink-0" />
                  <span>Ruas Jalan Poros Desa</span>
                </span>
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider"
                  :class="
                    activeKategori === 'ruas'
                      ? 'bg-emerald-600/12 text-emerald-800 dark:bg-white/20 dark:text-white'
                      : 'bg-white/70 dark:bg-white/[0.06] text-slate-600 dark:text-slate-400'
                  "
                >
                  Dalam Database
                </span>
              </button>

              <button
                type="button"
                role="tab"
                :aria-selected="activeKategori === 'non_ruas'"
                class="min-h-[44px] inline-flex items-center justify-between sm:justify-start gap-2.5 px-4 sm:px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all cursor-pointer"
                :class="
                  activeKategori === 'non_ruas'
                    ? 'bg-white dark:bg-emerald-600 text-slate-900 dark:text-white shadow-xs'
                    : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                "
                @click="activeKategori = 'non_ruas'"
              >
                <span class="inline-flex items-center gap-2">
                  <UIcon name="i-lucide-git-commit-horizontal" class="w-4 h-4 shrink-0" />
                  <span>Jalan Desa Non-Ruas</span>
                </span>
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider"
                  :class="
                    activeKategori === 'non_ruas'
                      ? 'bg-emerald-600/12 text-emerald-800 dark:bg-white/20 dark:text-white'
                      : 'bg-white/70 dark:bg-white/[0.06] text-slate-600 dark:text-slate-400'
                  "
                >
                  Diluar Database
                </span>
              </button>
            </div>

            <NuxtLink
              to="/maps"
              class="min-h-[44px] inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-white dark:bg-[#0c1522] hover:bg-emerald-700 hover:text-white dark:hover:bg-emerald-600 text-xs font-semibold text-slate-700 dark:text-slate-200 transition-colors w-full sm:w-auto"
            >
              <UIcon name="i-lucide-map" class="w-4 h-4" />
              <span>Buka Peta Interaktif Fullscreen</span>
              <UIcon name="i-lucide-arrow-up-right" class="w-3.5 h-3.5" />
            </NuxtLink>
          </div>
        </header>

        <!-- 2. Executive KPI Telemetry & Global Composition Strip (3-State Responsive Grid) -->
        <section aria-label="Ringkasan Eksekutif Infrastruktur">
          <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5">
            <!-- KPI 1: Total Panjang Jaringan -->
            <div class="rounded-2xl bg-white dark:bg-[#0c1522] p-5 sm:p-6 flex flex-col justify-between">
              <div>
                <div class="flex items-center justify-between gap-2 mb-2.5">
                  <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    Total Panjang Jaringan
                  </span>
                  <span class="w-9 h-9 rounded-xl bg-[#EAEFE9] dark:bg-white/[0.06] flex items-center justify-center text-slate-700 dark:text-slate-300">
                    <UIcon name="i-lucide-ruler" class="w-4 h-4" />
                  </span>
                </div>
                <p class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white tabular-nums tracking-tight">
                  {{ formatKm(summaryTotals.totalPanjangMeter) }}
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400 tabular-nums mt-1">
                  Setara {{ formatMetersExact(summaryTotals.totalPanjangMeter) }}
                </p>
              </div>
              <div class="mt-4 pt-3.5 border-t border-[#ECEFEB] dark:border-white/[0.06] flex items-center justify-between text-xs text-slate-600 dark:text-slate-400">
                <span>Cakupan Wilayah</span>
                <strong class="font-semibold text-slate-900 dark:text-white tabular-nums">
                  {{ summaryTotals.totalKecamatan }} Kec · {{ formatNumber(summaryTotals.totalDesa) }} Desa
                </strong>
              </div>
            </div>

            <!-- KPI 2: Realisasi Fisik Terbangun -->
            <div class="rounded-2xl bg-white dark:bg-[#0c1522] p-5 sm:p-6 flex flex-col justify-between">
              <div>
                <div class="flex items-center justify-between gap-2 mb-2.5">
                  <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                    Sudah Terbangun
                  </span>
                  <span class="w-9 h-9 rounded-xl bg-emerald-500/12 flex items-center justify-center text-emerald-700 dark:text-emerald-400">
                    <UIcon name="i-lucide-check-circle-2" class="w-4 h-4" />
                  </span>
                </div>
                <p class="text-2xl sm:text-3xl font-bold text-emerald-700 dark:text-emerald-400 tabular-nums tracking-tight">
                  {{ formatKm(summaryTotals.totalPanjangDibangun) }}
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400 tabular-nums mt-1">
                  {{ formatMetersExact(summaryTotals.totalPanjangDibangun) }} terealisasi
                </p>
              </div>
              <div class="mt-4 pt-3.5 border-t border-[#ECEFEB] dark:border-white/[0.06] flex items-center justify-between text-xs text-slate-600 dark:text-slate-400">
                <span>Total Segmen Fisik</span>
                <strong class="font-semibold text-emerald-700 dark:text-emerald-400 tabular-nums">
                  {{ formatNumber(summaryTotals.totalSegmen) }} segmen
                </strong>
              </div>
            </div>

            <!-- KPI 3: Sisa Belum Terbangun -->
            <div class="rounded-2xl bg-white dark:bg-[#0c1522] p-5 sm:p-6 flex flex-col justify-between">
              <div>
                <div class="flex items-center justify-between gap-2 mb-2.5">
                  <span class="text-xs font-semibold uppercase tracking-wider text-orange-700 dark:text-orange-400">
                    Belum Terbangun
                  </span>
                  <span class="w-9 h-9 rounded-xl bg-orange-500/12 flex items-center justify-center text-orange-700 dark:text-orange-400">
                    <UIcon name="i-lucide-clock" class="w-4 h-4" />
                  </span>
                </div>
                <p class="text-2xl sm:text-3xl font-bold text-orange-600 dark:text-orange-400 tabular-nums tracking-tight">
                  {{ formatKm(summaryTotals.totalPanjangBelum) }}
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400 tabular-nums mt-1">
                  {{ formatMetersExact(summaryTotals.totalPanjangBelum) }} sisa penanganan
                </p>
              </div>
              <div class="mt-4 pt-3.5 border-t border-[#ECEFEB] dark:border-white/[0.06] flex items-center justify-between text-xs text-slate-600 dark:text-slate-400">
                <span>Proporsi Sisa</span>
                <strong class="font-semibold text-orange-600 dark:text-orange-400 tabular-nums">
                  {{ formatPercent(summaryTotals.totalPanjangMeter > 0 ? 100 - summaryTotals.rataRataProgress : 0) }}
                </strong>
              </div>
            </div>

            <!-- KPI 4: Rata-rata Capaian Fisik & Donut Gauge -->
            <div class="rounded-2xl bg-[#EAEFE9]/90 dark:bg-[#0c1522] p-5 sm:p-6 flex items-center justify-between gap-4">
              <div class="space-y-1.5">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 block">
                  Capaian Fisik Daerah
                </span>
                <p class="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white tabular-nums tracking-tight">
                  {{ formatPercent(summaryTotals.rataRataProgress) }}
                </p>
                <p class="text-xs text-slate-600 dark:text-slate-400">
                  Dari <strong class="font-semibold text-slate-900 dark:text-white tabular-nums">{{ formatNumber(summaryTotals.totalRuas) }}</strong>
                  {{ activeKategori === 'ruas' ? 'ruas poros desa' : 'segmen non-ruas' }}
                </p>
              </div>

              <!-- Clean SVG Ring Gauge -->
              <div class="relative w-20 h-20 sm:w-24 sm:h-24 shrink-0 flex items-center justify-center">
                <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100">
                  <circle
                    cx="50"
                    cy="50"
                    r="42"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="10"
                    class="text-white/80 dark:text-white/[0.08]"
                  />
                  <circle
                    cx="50"
                    cy="50"
                    r="42"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="10"
                    stroke-linecap="round"
                    :stroke-dasharray="donutCircumference"
                    :stroke-dashoffset="donutStrokeDashoffset"
                    class="text-emerald-600 dark:text-emerald-400 transition-all duration-700"
                  />
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                  <span class="text-xs font-bold text-slate-900 dark:text-white tabular-nums">
                    {{ Math.round(summaryTotals.rataRataProgress) }}%
                  </span>
                  <span class="text-[10px] text-slate-500 dark:text-slate-400">Tuntas</span>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- 3. Visual Analytics Suite (Grafik Komparasi Wilayah & Distribusi Klaster) -->
        <section aria-label="Visualisasi Grafik Statistik Jalan" class="grid grid-cols-1 lg:grid-cols-12 gap-5 sm:gap-6 items-stretch">

          <!-- Left 8 Cols: Interactive Stacked Horizontal Bar Chart per Kecamatan (Sorted by Highest Total Km, Flush Bottom) -->
          <div class="lg:col-span-8 rounded-2xl bg-white dark:bg-[#0c1522] p-5 sm:p-7 flex flex-col justify-between">
            <div class="flex-1 flex flex-col min-h-0">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 mb-4 border-b border-[#ECEFEB] dark:border-white/[0.06] shrink-0">
                <div>
                  <span class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400 block mb-1">
                    Grafik Komparasi Spasial Antar Kecamatan · Urut Total Km Tertinggi
                  </span>
                  <h2 class="text-base sm:text-xl font-bold text-slate-900 dark:text-white tracking-tight">
                    Proporsi Panjang Terbangun vs Sisa Belum Terbangun (km)
                  </h2>
                </div>

                <!-- Chart Display Controls (44px touch targets) -->
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                  <div class="inline-flex items-center p-1 rounded-xl bg-[#F4F7F3] dark:bg-white/[0.04] text-xs w-full sm:w-auto">
                    <button
                      type="button"
                      class="flex-1 sm:flex-initial min-h-[44px] px-3.5 py-1.5 rounded-lg font-semibold transition-colors cursor-pointer"
                      :class="
                        chartDisplayLimit === 'all'
                          ? 'bg-white dark:bg-emerald-600 text-slate-900 dark:text-white shadow-xs'
                          : 'text-slate-600 dark:text-slate-400'
                      "
                      @click="chartDisplayLimit = 'all'"
                    >
                      Semua ({{ chartSortedByPanjang.length }})
                    </button>
                    <button
                      type="button"
                      class="flex-1 sm:flex-initial min-h-[44px] px-3.5 py-1.5 rounded-lg font-semibold transition-colors cursor-pointer"
                      :class="
                        chartDisplayLimit === 'top14'
                          ? 'bg-white dark:bg-emerald-600 text-slate-900 dark:text-white shadow-xs'
                          : 'text-slate-600 dark:text-slate-400'
                      "
                      @click="chartDisplayLimit = 'top14'"
                    >
                      14 Teratas
                    </button>
                  </div>
                </div>
              </div>

              <!-- Legend Key -->
              <div class="flex flex-wrap items-center justify-between gap-3 mb-4 text-xs text-slate-600 dark:text-slate-400 shrink-0">
                <div class="flex flex-wrap items-center gap-4 sm:gap-5">
                  <div class="inline-flex items-center gap-2">
                    <span class="w-3 h-3 rounded-sm bg-emerald-600 dark:bg-emerald-500"></span>
                    <span>Panjang Terbangun (km)</span>
                  </div>
                  <div class="inline-flex items-center gap-2">
                    <span class="w-3 h-3 rounded-sm bg-orange-400/85 dark:bg-orange-500/70"></span>
                    <span>Sisa Belum Terbangun (km)</span>
                  </div>
                </div>
                <span class="text-[11px] text-slate-500 dark:text-slate-400">
                  Ketuk baris grafik untuk membuka rincian desa pada tabel
                </span>
              </div>

              <!-- Bar Chart Rows -->
              <div v-if="loadingKecamatan" class="space-y-3 py-6 flex-1">
                <div
                  v-for="n in 9"
                  :key="n"
                  class="h-10 rounded-xl bg-[#F4F7F3] dark:bg-white/[0.03] animate-pulse"
                ></div>
              </div>

              <div
                v-else-if="chartKecamatanItems.length > 0"
                class="space-y-2 max-h-[480px] sm:max-h-[560px] lg:max-h-[600px] overflow-y-auto pr-1 flex-1"
              >
                <button
                  v-for="(kec, cIdx) in chartKecamatanItems"
                  :key="kec.id_kecamatan"
                  type="button"
                  class="w-full min-h-[48px] group text-left p-2.5 sm:p-3 rounded-xl hover:bg-[#F7F8F5] dark:hover:bg-white/[0.03] active:bg-[#EAEFE9] transition-colors cursor-pointer"
                  @click="focusAndExpandKecamatan(kec)"
                >
                  <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 text-xs mb-1.5">
                    <div class="flex items-center gap-2 min-w-0">
                      <span class="w-5 text-[11px] font-bold text-slate-400 dark:text-slate-500 tabular-nums shrink-0">
                        {{ cIdx + 1 }}.
                      </span>
                      <span class="font-bold text-slate-800 dark:text-slate-200 group-hover:text-emerald-700 dark:group-hover:text-emerald-400 truncate">
                        {{ kec.nama_kecamatan }}
                      </span>
                      <span class="text-[11px] text-slate-400 dark:text-slate-500 tabular-nums shrink-0">
                        {{ kec.jumlah_desa }} desa · {{ kec.jumlah_ruas }} ruas
                      </span>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-3 shrink-0 tabular-nums ms-auto">
                      <span class="text-emerald-700 dark:text-emerald-400 font-semibold">
                        {{ formatKm(kec.panjang_dibangun) }}
                      </span>
                      <span class="text-slate-400">/</span>
                      <span class="font-bold text-slate-900 dark:text-white">
                        {{ formatKm(kec.total_panjang) }}
                      </span>
                      <span
                        class="px-2 py-0.5 rounded-md text-[11px] font-bold w-16 text-right"
                        :class="
                          kec.progress >= 80
                            ? 'bg-emerald-500/12 text-emerald-800 dark:text-emerald-300'
                            : kec.progress >= 60
                              ? 'bg-teal-500/12 text-teal-800 dark:text-teal-300'
                              : kec.progress >= 40
                                ? 'bg-amber-500/12 text-amber-800 dark:text-amber-300'
                                : 'bg-orange-500/12 text-orange-800 dark:text-orange-300'
                        "
                      >
                        {{ formatPercent(kec.progress) }}
                      </span>
                    </div>
                  </div>

                  <!-- Proportional Stacked Bar relative to Largest Kecamatan Length -->
                  <div class="w-full h-3 rounded-full bg-[#EAEFE9] dark:bg-white/[0.06] overflow-hidden flex">
                    <div
                      class="h-full bg-emerald-600 dark:bg-emerald-500 transition-all duration-500"
                      :style="{
                        width: `${Math.min((Number(kec.panjang_dibangun || 0) / maxKecamatanPanjang) * 100, 100)}%`,
                      }"
                      :title="`Terbangun: ${formatKm(kec.panjang_dibangun)}`"
                    ></div>
                    <div
                      class="h-full bg-orange-400/85 dark:bg-orange-500/70 transition-all duration-500"
                      :style="{
                        width: `${Math.min((Number(kec.panjang_belum || 0) / maxKecamatanPanjang) * 100, 100)}%`,
                      }"
                      :title="`Belum Terbangun: ${formatKm(kec.panjang_belum)}`"
                    ></div>
                  </div>
                </button>
              </div>
            </div>

            <!-- Tonal Summary Anchor Strip at Bottom of Left Card (Eliminates Empty Bottom Gap) -->
            <div class="mt-5 pt-4 border-t border-[#ECEFEB] dark:border-white/[0.06] grid grid-cols-1 sm:grid-cols-3 gap-3 shrink-0">
              <div class="p-3 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 block">
                  Jaringan Terpanjang
                </span>
                <p class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white tabular-nums mt-0.5 truncate">
                  {{ longestNetworkKecamatan?.nama_kecamatan || '-' }} · {{ formatKm(longestNetworkKecamatan?.total_panjang) }}
                </p>
              </div>

              <div class="p-3 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 block">
                  Rata-rata per Kecamatan
                </span>
                <p class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white tabular-nums mt-0.5">
                  {{ formatKm(averagePanjangPerKecamatan) }} / kecamatan
                </p>
              </div>

              <div class="p-3 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03]">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 block">
                  Realisasi Total
                </span>
                <p class="text-xs sm:text-sm font-bold text-emerald-700 dark:text-emerald-400 tabular-nums mt-0.5">
                  {{ formatKm(summaryTotals.totalPanjangDibangun) }} dari {{ formatKm(summaryTotals.totalPanjangMeter) }}
                </p>
              </div>
            </div>
          </div>

          <!-- Right 4 Cols: Readiness Clusters & Top/Bottom Ranking Insights -->
          <div class="lg:col-span-4 flex flex-col gap-5 sm:gap-6">

            <!-- Card A: Distribusi Klaster Capaian Kecamatan (Interactive Filter, 48px touch target) -->
            <div class="rounded-2xl bg-white dark:bg-[#0c1522] p-5 sm:p-6">
              <div class="flex items-center justify-between gap-2 mb-1">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                  Klaster Kinerja Wilayah
                </span>
                <button
                  v-if="selectedCluster !== 'all'"
                  type="button"
                  class="min-h-[36px] px-2 text-xs font-semibold text-emerald-700 dark:text-emerald-400 hover:underline cursor-pointer"
                  @click="selectedCluster = 'all'"
                >
                  Tampilkan Semua
                </button>
              </div>
              <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">
                Distribusi Capaian {{ kecamatanList.length }} Kecamatan
              </h3>

              <div class="space-y-2.5">
                <button
                  v-for="cl in readinessClusters"
                  :key="cl.key"
                  type="button"
                  class="w-full min-h-[48px] p-3 sm:p-3.5 rounded-xl text-left transition-all cursor-pointer"
                  :class="
                    selectedCluster === cl.key
                      ? 'bg-[#EAEFE9] dark:bg-emerald-950/40 ring-1 ring-emerald-600/40'
                      : 'bg-[#F7F8F5] dark:bg-white/[0.03] hover:bg-[#EAEFE9]/70 dark:hover:bg-white/[0.06]'
                  "
                  @click="selectedCluster = selectedCluster === cl.key ? 'all' : cl.key"
                >
                  <div class="flex flex-wrap items-center justify-between gap-2 text-xs mb-1.5">
                    <div class="flex items-center gap-2">
                      <span class="font-bold text-slate-900 dark:text-white">{{ cl.label }}</span>
                      <span class="px-2 py-0.5 rounded text-[10px] font-semibold tabular-nums" :class="cl.badgeClass">
                        {{ cl.range }}
                      </span>
                    </div>
                    <span class="font-bold text-slate-900 dark:text-white tabular-nums">
                      {{ cl.count }} kec ({{ cl.share }}%)
                    </span>
                  </div>
                  <div class="w-full h-2 rounded-full bg-white dark:bg-white/[0.07] overflow-hidden">
                    <div
                      class="h-full rounded-full transition-all duration-500"
                      :class="cl.barClass"
                      :style="{ width: `${cl.share}%` }"
                    ></div>
                  </div>
                </button>
              </div>
            </div>

            <!-- Card B: Top 5 Capaian Tertinggi vs Top 5 Backlog Terbesar -->
            <div class="rounded-2xl bg-white dark:bg-[#0c1522] p-5 sm:p-6 flex-1 flex flex-col justify-between space-y-5">
              <!-- Top 5 Capaian Tertinggi -->
              <div>
                <div class="flex items-center justify-between gap-2 mb-3">
                  <span class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                    5 Kecamatan Progres Tertinggi
                  </span>
                  <UIcon name="i-lucide-award" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div class="space-y-2">
                  <div
                    v-for="(item, idx) in topProgressKecamatan"
                    :key="item.id_kecamatan"
                    class="flex items-center justify-between gap-2 py-2 px-3 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03] text-xs"
                  >
                    <div class="flex items-center gap-2 min-w-0">
                      <span class="w-5 h-5 rounded-full bg-emerald-600/15 text-emerald-800 dark:text-emerald-300 font-bold text-[11px] flex items-center justify-center tabular-nums shrink-0">
                        {{ idx + 1 }}
                      </span>
                      <span class="font-semibold text-slate-800 dark:text-slate-200 truncate">
                        {{ item.nama_kecamatan }}
                      </span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0 tabular-nums">
                      <span class="text-slate-500 dark:text-slate-400">{{ formatKm(item.panjang_dibangun) }}</span>
                      <strong class="text-emerald-700 dark:text-emerald-400 font-bold w-14 text-right">
                        {{ formatPercent(item.progress) }}
                      </strong>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Top 5 Sisa Belum Terbangun Terbesar -->
              <div class="pt-4 border-t border-[#ECEFEB] dark:border-white/[0.06]">
                <div class="flex items-center justify-between gap-2 mb-3">
                  <span class="text-xs font-bold uppercase tracking-wider text-orange-700 dark:text-orange-400">
                    5 Sisa Belum Terbangun Terbesar
                  </span>
                  <UIcon name="i-lucide-alert-triangle" class="w-4 h-4 text-orange-600 dark:text-orange-400" />
                </div>
                <div class="space-y-2">
                  <div
                    v-for="(item, idx) in topBacklogKecamatan"
                    :key="item.id_kecamatan"
                    class="flex items-center justify-between gap-2 py-2 px-3 rounded-xl bg-[#F7F8F5] dark:bg-white/[0.03] text-xs"
                  >
                    <div class="flex items-center gap-2 min-w-0">
                      <span class="w-5 h-5 rounded-full bg-orange-500/15 text-orange-800 dark:text-orange-300 font-bold text-[11px] flex items-center justify-center tabular-nums shrink-0">
                        {{ idx + 1 }}
                      </span>
                      <span class="font-semibold text-slate-800 dark:text-slate-200 truncate">
                        {{ item.nama_kecamatan }}
                      </span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0 tabular-nums">
                      <strong class="text-orange-600 dark:text-orange-400 font-bold">
                        {{ formatKm(item.panjang_belum) }}
                      </strong>
                      <span class="text-slate-400 text-[11px]">belum</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </section>

        <!-- 4. Filter, Search & Sorting Control Bar (3-State Responsive Grid) -->
        <section aria-label="Filter dan Pencarian Data Tabel" class="rounded-2xl bg-[#EAEFE9]/80 dark:bg-[#0c1522] p-4 sm:p-6">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3.5">
            <!-- Cari Kecamatan -->
            <div>
              <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                Cari Kecamatan
              </label>
              <div class="relative">
                <UIcon
                  name="i-lucide-search"
                  class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"
                />
                <input
                  v-model="searchQuery"
                  type="search"
                  placeholder="Ketik nama kecamatan..."
                  class="w-full min-h-[44px] pl-10 pr-4 py-2 rounded-xl bg-white dark:bg-[#132033] text-xs font-medium text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-600/30"
                />
              </div>
            </div>

            <!-- Filter Tahun Anggaran -->
            <div>
              <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                Tahun Anggaran
              </label>
              <select
                v-model="selectedTahun"
                aria-label="Filter Tahun Anggaran"
                class="w-full min-h-[44px] px-3.5 py-2 rounded-xl bg-white dark:bg-[#132033] text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-600/30 cursor-pointer"
              >
                <option v-for="opt in tahunOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <!-- Filter Kondisi Fisik -->
            <div>
              <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                Kondisi Perkerasan
              </label>
              <select
                v-model="selectedKondisi"
                aria-label="Filter Kondisi Jalan"
                class="w-full min-h-[44px] px-3.5 py-2 rounded-xl bg-white dark:bg-[#132033] text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-600/30 cursor-pointer"
              >
                <option v-for="opt in kondisiOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <!-- Filter Sumber Dana -->
            <div>
              <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                Sumber Pendanaan
              </label>
              <select
                v-model="selectedSumberDana"
                aria-label="Filter Sumber Dana"
                class="w-full min-h-[44px] px-3.5 py-2 rounded-xl bg-white dark:bg-[#132033] text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-600/30 cursor-pointer"
              >
                <option v-for="opt in sumberDanaOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <!-- Urutan Data -->
            <div>
              <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                Urutkan Kecamatan
              </label>
              <select
                v-model="sortBy"
                aria-label="Urutkan Kecamatan"
                class="w-full min-h-[44px] px-3.5 py-2 rounded-xl bg-white dark:bg-[#132033] text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-600/30 cursor-pointer"
              >
                <option value="nama_asc">Wilayah Administrasi (A ke Z)</option>
                <option value="nama_desc">Wilayah Administrasi (Z ke A)</option>
                <option value="panjang_desc">Total Panjang Terbesar</option>
                <option value="progress_desc">Capaian Progres Tertinggi</option>
                <option value="belum_desc">Sisa Belum Terpanjang</option>
              </select>
            </div>
          </div>

          <!-- Active Filter Summary & Collapse Helper -->
          <div class="mt-4 pt-3 border-t border-[#DCE3DA] dark:border-white/[0.07] flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex flex-wrap items-center gap-2 text-slate-600 dark:text-slate-400">
              <span>
                Menampilkan <strong class="font-bold text-slate-900 dark:text-white tabular-nums">{{ filteredKecamatanList.length }}</strong> kecamatan
              </span>
              <span v-if="expandedKecamatan.size > 0" class="text-emerald-700 dark:text-emerald-400 font-semibold">
                · {{ expandedKecamatan.size }} kecamatan sedang dibuka rincian desanya
              </span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <button
                v-if="expandedKecamatan.size > 0"
                type="button"
                class="min-h-[44px] inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-white dark:bg-[#132033] text-xs font-semibold text-slate-700 dark:text-slate-200 hover:text-emerald-700 transition-colors cursor-pointer"
                @click="collapseAllRows"
              >
                <UIcon name="i-lucide-fold-vertical" class="w-3.5 h-3.5" />
                <span>Tutup Semua Baris Rincian</span>
              </button>

              <button
                v-if="hasActiveFilter"
                type="button"
                class="min-h-[44px] inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-900 text-white dark:bg-emerald-600 text-xs font-semibold transition-colors cursor-pointer"
                @click="handleResetFilter"
              >
                <UIcon name="i-lucide-rotate-ccw" class="w-3.5 h-3.5" />
                <span>Atur Ulang Semua Filter</span>
              </button>
            </div>
          </div>
        </section>

        <!-- 5. Tabel Eksplorasi Hierarki 4-Level (Kecamatan -> Desa -> Ruas -> Segmen Fisik) -->
        <section aria-label="Tabel Rekapitulasi Hierarki 4 Level" class="space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-2">
            <div>
              <span class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400 block mb-1">
                Matriks Data Tabular Berjenjang (4 Level)
              </span>
              <h2 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                Rincian Pembangunan Infrastruktur Jalan
              </h2>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              Klik pada baris <strong class="font-semibold text-slate-800 dark:text-slate-200">Kecamatan</strong> untuk membuka daftar <strong class="font-semibold text-slate-800 dark:text-slate-200">Desa</strong>, lalu klik <strong class="font-semibold text-slate-800 dark:text-slate-200">Desa</strong> untuk menginspeksi <strong class="font-semibold text-slate-800 dark:text-slate-200">Ruas &amp; Peta Spasial</strong>.
            </p>
          </div>

          <div class="rounded-2xl bg-white dark:bg-[#0c1522] overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="bg-[#EAEFE9]/80 dark:bg-white/[0.04] text-slate-600 dark:text-slate-300 font-bold uppercase tracking-wider text-[11px]">
                    <th class="py-4 px-4 w-14 text-center">No</th>
                    <th class="py-4 px-4 min-w-[250px]">
                      <button
                        type="button"
                        class="inline-flex items-center gap-1.5 uppercase tracking-wider font-bold text-slate-700 dark:text-slate-200 hover:text-emerald-700 dark:hover:text-emerald-400 transition-colors cursor-pointer"
                        @click="sortBy = sortBy === 'nama_asc' ? 'nama_desc' : 'nama_asc'"
                      >
                        <span>Wilayah Administrasi / Ruas Jalan</span>
                        <UIcon
                          :name="
                            sortBy === 'nama_asc'
                              ? 'i-lucide-arrow-up-a-z'
                              : sortBy === 'nama_desc'
                                ? 'i-lucide-arrow-down-z-a'
                                : 'i-lucide-arrow-up-down'
                          "
                          class="w-3.5 h-3.5 text-emerald-700 dark:text-emerald-400"
                        />
                      </button>
                    </th>
                    <th class="py-4 px-4 text-center min-w-[120px]">Cakupan</th>
                    <th class="py-4 px-4 text-right min-w-[135px]">Total Panjang</th>
                    <th class="py-4 px-4 text-right min-w-[135px]">Sudah Terbangun</th>
                    <th class="py-4 px-4 text-right min-w-[135px]">Belum Terbangun</th>
                    <th class="py-4 px-4 min-w-[175px]">Capaian Fisik</th>
                    <th class="py-4 px-4 text-center w-36">Eksplorasi / Peta</th>
                  </tr>
                </thead>

                <tbody class="divide-y divide-[#ECEFEB] dark:divide-white/[0.05]">
                  <!-- Loading State -->
                  <tr v-if="loadingKecamatan">
                    <td colspan="8" class="py-16 text-center text-slate-500 dark:text-slate-400">
                      <div class="flex flex-col items-center justify-center gap-3">
                        <UIcon name="i-lucide-loader-2" class="w-7 h-7 animate-spin text-emerald-600 dark:text-emerald-400" />
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                          Memuat rekapitulasi statistik infrastruktur jalan se-Kabupaten Bojonegoro...
                        </p>
                      </div>
                    </td>
                  </tr>

                  <!-- Error State -->
                  <tr v-else-if="errorKecamatan">
                    <td colspan="8" class="py-14 text-center">
                      <div class="max-w-md mx-auto space-y-3">
                        <UIcon name="i-lucide-alert-circle" class="w-7 h-7 text-amber-500 mx-auto" />
                        <p class="text-sm font-bold text-slate-900 dark:text-white">
                          Gagal Memuat Rekapitulasi Statistik
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                          {{ errorKecamatan }}
                        </p>
                        <button
                          type="button"
                          class="min-h-[44px] inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-700 text-white text-xs font-semibold cursor-pointer"
                          @click="loadKecamatan"
                        >
                          <UIcon name="i-lucide-refresh-cw" class="w-4 h-4" />
                          <span>Coba Lagi</span>
                        </button>
                      </div>
                    </td>
                  </tr>

                  <!-- Empty State -->
                  <tr v-else-if="filteredKecamatanList.length === 0">
                    <td colspan="8" class="py-16 text-center text-slate-500">
                      <div class="flex flex-col items-center justify-center gap-2">
                        <UIcon name="i-lucide-search-x" class="w-8 h-8 text-slate-400" />
                        <span class="text-sm font-bold text-slate-800 dark:text-slate-200">
                          Tidak ada kecamatan yang sesuai dengan filter
                        </span>
                        <span class="text-xs text-slate-500">
                          Silakan atur ulang kata kunci atau kombinasi filter di atas.
                        </span>
                      </div>
                    </td>
                  </tr>

                  <!-- LEVEL 1: BARIS KECAMATAN -->
                  <template v-else v-for="(kec, idx) in filteredKecamatanList" :key="kec.id_kecamatan">
                    <tr
                      :id="`row-kec-${kec.id_kecamatan}`"
                      class="hover:bg-[#F7F8F5] dark:hover:bg-white/[0.02] transition-colors cursor-pointer group"
                      :class="{ 'bg-[#F4F7F3] dark:bg-emerald-950/15': expandedKecamatan.has(kec.id_kecamatan) }"
                      @click="toggleKecamatan(kec)"
                    >
                      <td class="py-3.5 px-4 text-center">
                        <div class="flex items-center justify-center gap-1.5">
                          <span
                            class="w-6 h-6 rounded-lg flex items-center justify-center text-slate-400 group-hover:text-slate-800 dark:group-hover:text-white transition-transform"
                            :class="{ 'rotate-90 text-emerald-700 dark:text-emerald-400': expandedKecamatan.has(kec.id_kecamatan) }"
                          >
                            <UIcon name="i-lucide-chevron-right" class="w-4 h-4" />
                          </span>
                          <span class="tabular-nums text-slate-400 font-semibold text-[11px]">{{ idx + 1 }}</span>
                        </div>
                      </td>

                      <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">
                        <div class="flex items-center gap-2.5">
                          <span class="w-2 h-2 rounded-full bg-emerald-600 dark:bg-emerald-400 shrink-0"></span>
                          <span class="text-xs sm:text-sm">Kecamatan {{ kec.nama_kecamatan }}</span>
                        </div>
                      </td>

                      <td class="py-3.5 px-4 text-center tabular-nums text-slate-600 dark:text-slate-300">
                        <strong class="font-bold text-slate-900 dark:text-white">{{ kec.jumlah_desa }}</strong> desa
                        <span class="text-slate-300 dark:text-slate-600 mx-1">·</span>
                        <strong class="font-bold text-slate-900 dark:text-white">{{ kec.jumlah_ruas }}</strong> ruas
                      </td>

                      <td class="py-3.5 px-4 text-right tabular-nums font-bold text-slate-900 dark:text-white">
                        {{ formatKm(kec.total_panjang) }}
                      </td>

                      <td class="py-3.5 px-4 text-right tabular-nums font-bold text-emerald-700 dark:text-emerald-400">
                        {{ formatKm(kec.panjang_dibangun) }}
                      </td>

                      <td class="py-3.5 px-4 text-right tabular-nums font-semibold text-orange-600 dark:text-orange-400">
                        {{ formatKm(kec.panjang_belum) }}
                      </td>

                      <td class="py-3.5 px-4">
                        <div class="flex items-center gap-2.5">
                          <div class="flex-1 bg-[#EAEFE9] dark:bg-white/[0.08] h-2 rounded-full overflow-hidden">
                            <div
                              class="h-full rounded-full transition-all duration-300"
                              :class="getProgressBarClass(kec.progress)"
                              :style="{ width: `${Math.min(Math.max(kec.progress, 0), 100)}%` }"
                            />
                          </div>
                          <span class="tabular-nums font-bold text-[11px] w-14 text-right text-slate-800 dark:text-slate-200">
                            {{ formatPercent(kec.progress) }}
                          </span>
                        </div>
                      </td>

                      <td class="py-3.5 px-4 text-center" @click.stop>
                        <button
                          type="button"
                          class="min-h-[44px] inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold transition-colors cursor-pointer"
                          :class="
                            expandedKecamatan.has(kec.id_kecamatan)
                              ? 'bg-slate-900 text-white dark:bg-emerald-600'
                              : 'bg-[#EAEFE9] dark:bg-white/[0.06] text-slate-700 dark:text-slate-200 hover:bg-emerald-700 hover:text-white'
                          "
                          @click="toggleKecamatan(kec)"
                        >
                          <span>{{ expandedKecamatan.has(kec.id_kecamatan) ? 'Tutup' : 'Rincian Desa' }}</span>
                          <UIcon
                            :name="expandedKecamatan.has(kec.id_kecamatan) ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
                            class="w-3.5 h-3.5"
                          />
                        </button>
                      </td>
                    </tr>

                    <!-- LEVEL 2: BARIS DESA DI DALAM KECAMATAN -->
                    <template v-if="expandedKecamatan.has(kec.id_kecamatan)">
                      <tr v-if="loadingDesaMap[kec.id_kecamatan]" class="bg-[#F7F8F5] dark:bg-[#080d16]">
                        <td colspan="8" class="py-4 px-12 text-slate-500">
                          <div class="flex items-center gap-2">
                            <UIcon name="i-lucide-loader-2" class="w-4 h-4 animate-spin text-emerald-600" />
                            <span>Memuat daftar desa di Kecamatan {{ kec.nama_kecamatan }}...</span>
                          </div>
                        </td>
                      </tr>

                      <tr
                        v-else-if="!desaDataMap[kec.id_kecamatan] || desaDataMap[kec.id_kecamatan]?.length === 0"
                        class="bg-[#F7F8F5] dark:bg-[#080d16]"
                      >
                        <td colspan="8" class="py-4 px-12 text-slate-400">
                          Belum ada data desa tercatat untuk kecamatan ini.
                        </td>
                      </tr>

                      <template v-else v-for="desa in desaDataMap[kec.id_kecamatan]" :key="desa.id_desa">
                        <tr
                          class="bg-[#F7F8F5]/90 dark:bg-[#080d16] hover:bg-[#EAEFE9]/60 dark:hover:bg-white/[0.03] transition-colors cursor-pointer"
                          :class="{ 'bg-emerald-500/[0.06] dark:bg-emerald-950/20': expandedDesa.has(desa.id_desa) }"
                          @click="toggleDesa(desa)"
                        >
                          <td class="py-3 px-4 text-center">
                            <div class="flex items-center justify-end pe-1">
                              <span
                                class="w-5 h-5 rounded flex items-center justify-center text-slate-400 transition-transform"
                                :class="{ 'rotate-90 text-emerald-700 dark:text-emerald-400': expandedDesa.has(desa.id_desa) }"
                              >
                                <UIcon name="i-lucide-chevron-right" class="w-3.5 h-3.5" />
                              </span>
                            </div>
                          </td>

                          <td class="py-3 px-4 font-semibold text-slate-800 dark:text-slate-200">
                            <div class="flex items-center gap-2 ps-4 border-s-2 border-emerald-600/50 dark:border-emerald-500/50">
                              <UIcon name="i-lucide-map-pin" class="w-3.5 h-3.5 text-emerald-700 dark:text-emerald-400 shrink-0" />
                              <span>Desa {{ desa.nama_desa }}</span>
                            </div>
                          </td>

                          <td class="py-3 px-4 text-center tabular-nums text-slate-600 dark:text-slate-300">
                            {{ desa.jumlah_ruas }} ruas
                          </td>

                          <td class="py-3 px-4 text-right tabular-nums font-semibold text-slate-800 dark:text-slate-200">
                            {{ formatMeterOrKm(desa.total_panjang) }}
                          </td>

                          <td class="py-3 px-4 text-right tabular-nums font-semibold text-emerald-700 dark:text-emerald-400">
                            {{ formatMeterOrKm(desa.panjang_dibangun) }}
                          </td>

                          <td class="py-3 px-4 text-right tabular-nums text-orange-600 dark:text-orange-400">
                            {{ formatMeterOrKm(desa.panjang_belum) }}
                          </td>

                          <td class="py-3 px-4">
                            <div class="flex items-center gap-2">
                              <div class="flex-1 bg-white dark:bg-white/[0.07] h-1.5 rounded-full overflow-hidden">
                                <div
                                  class="h-full rounded-full"
                                  :class="getProgressBarClass(desa.progress)"
                                  :style="{ width: `${Math.min(Math.max(desa.progress, 0), 100)}%` }"
                                />
                              </div>
                              <span class="tabular-nums font-semibold text-[11px] w-14 text-right text-slate-700 dark:text-slate-300">
                                {{ formatPercent(desa.progress) }}
                              </span>
                            </div>
                          </td>

                          <td class="py-3 px-4 text-center" @click.stop>
                            <button
                              type="button"
                              class="min-h-[44px] inline-flex items-center justify-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-white dark:bg-white/[0.05] hover:bg-emerald-700 hover:text-white text-[11px] font-semibold text-slate-700 dark:text-slate-300 transition-colors cursor-pointer"
                              @click="toggleDesa(desa)"
                            >
                              <span>{{ expandedDesa.has(desa.id_desa) ? 'Tutup Ruas' : 'Lihat Ruas' }}</span>
                              <UIcon
                                :name="expandedDesa.has(desa.id_desa) ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
                                class="w-3.5 h-3.5"
                              />
                            </button>
                          </td>
                        </tr>

                        <!-- LEVEL 3: BARIS RUAS JALAN / SEGMEN MANDIRI DI DALAM DESA -->
                        <template v-if="expandedDesa.has(desa.id_desa)">
                          <tr v-if="loadingRuasMap[desa.id_desa]" class="bg-white dark:bg-[#060a11]">
                            <td colspan="8" class="py-3.5 px-16 text-slate-500">
                              <div class="flex items-center gap-2">
                                <UIcon name="i-lucide-loader-2" class="w-4 h-4 animate-spin text-emerald-600" />
                                <span>Memuat daftar ruas jalan di Desa {{ desa.nama_desa }}...</span>
                              </div>
                            </td>
                          </tr>

                          <tr
                            v-else-if="!ruasDataMap[desa.id_desa] || ruasDataMap[desa.id_desa]?.length === 0"
                            class="bg-white dark:bg-[#060a11]"
                          >
                            <td colspan="8" class="py-3.5 px-16 text-slate-400">
                              Tidak ada ruas jalan tercatat untuk desa ini.
                            </td>
                          </tr>

                          <template v-else v-for="ruas in ruasDataMap[desa.id_desa]" :key="ruas.id">
                            <tr
                              class="bg-white dark:bg-[#060a11] hover:bg-[#F7F8F5] dark:hover:bg-white/[0.03] transition-colors"
                              :class="{ 'bg-amber-500/[0.05]': expandedRuas.has(ruas.id) }"
                            >
                              <td class="py-3 px-4 text-center">
                                <div class="flex items-center justify-end">
                                  <button
                                    v-if="ruas.jumlah_segmen > 0"
                                    type="button"
                                    class="min-h-[44px] min-w-[44px] rounded-xl flex items-center justify-center text-slate-400 hover:text-slate-800 dark:hover:text-white transition-transform cursor-pointer"
                                    :class="{ 'rotate-90 text-emerald-600': expandedRuas.has(ruas.id) }"
                                    title="Tampilkan rincian segmen fisik pembangunan"
                                    @click="toggleRuas(ruas)"
                                  >
                                    <UIcon name="i-lucide-chevron-right" class="w-4 h-4" />
                                  </button>
                                  <span v-else class="w-1.5 h-1.5 rounded-full bg-slate-300 dark:bg-slate-700 me-2" />
                                </div>
                              </td>

                              <td class="py-3 px-4">
                                <div class="ps-7 border-s-2 border-teal-500/60 space-y-1">
                                  <div class="flex items-center gap-1.5 flex-wrap">
                                    <span
                                      v-if="ruas.is_poros !== false && ruas.kode_ruas && ruas.kode_ruas !== '-'"
                                      class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/12 text-emerald-800 dark:text-emerald-300 tabular-nums"
                                    >
                                      Ruas {{ ruas.kode_ruas }}
                                    </span>
                                    <span
                                      v-else
                                      class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-teal-500/12 text-teal-800 dark:text-teal-300"
                                    >
                                      Segmen Mandiri
                                    </span>

                                    <span
                                      v-if="ruas.atribut?.jenis_perkerasan"
                                      class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-[#EAEFE9] dark:bg-white/[0.06] text-slate-700 dark:text-slate-300"
                                    >
                                      {{ ruas.atribut.jenis_perkerasan }}
                                    </span>

                                    <span
                                      v-if="ruas.kondisi"
                                      :class="[
                                        'inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold capitalize',
                                        getKondisiBadgeClass(ruas.kondisi),
                                      ]"
                                    >
                                      {{ ruas.kondisi }}
                                    </span>
                                  </div>

                                  <p class="font-bold text-slate-900 dark:text-white text-xs">
                                    {{ ruas.nama_ruas }}
                                  </p>
                                </div>
                              </td>

                              <td class="py-3 px-4 text-center tabular-nums">
                                <button
                                  v-if="ruas.jumlah_segmen > 0"
                                  type="button"
                                  class="min-h-[44px] px-2 text-emerald-700 dark:text-emerald-400 font-semibold hover:underline cursor-pointer"
                                  @click="toggleRuas(ruas)"
                                >
                                  {{ ruas.jumlah_segmen }} segmen fisik
                                </button>
                                <span v-else class="text-slate-400">-</span>
                              </td>

                              <td class="py-3 px-4 text-right tabular-nums font-semibold text-slate-900 dark:text-white">
                                {{ formatMeterOrKm(ruas.panjang_master || ruas.panjang) }}
                              </td>

                              <td class="py-3 px-4 text-right tabular-nums font-semibold text-emerald-700 dark:text-emerald-400">
                                {{ formatMeterOrKm(ruas.panjang_dibangun) }}
                              </td>

                              <td class="py-3 px-4 text-right tabular-nums text-orange-600 dark:text-orange-400">
                                {{ formatMeterOrKm(ruas.panjang_tersisa) }}
                              </td>

                              <td class="py-3 px-4">
                                <div class="flex items-center gap-2">
                                  <div class="flex-1 bg-[#EAEFE9] dark:bg-white/[0.08] h-1.5 rounded-full overflow-hidden">
                                    <div
                                      class="h-full rounded-full"
                                      :class="getProgressBarClass(ruas.progress)"
                                      :style="{ width: `${Math.min(Math.max(ruas.progress, 0), 100)}%` }"
                                    />
                                  </div>
                                  <span class="tabular-nums font-semibold text-[11px] w-14 text-right text-slate-700 dark:text-slate-300">
                                    {{ formatPercent(ruas.progress) }}
                                  </span>
                                </div>
                              </td>

                              <td class="py-3 px-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                  <button
                                    type="button"
                                    class="min-h-[44px] inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-[11px] font-semibold transition-colors cursor-pointer"
                                    title="Pratinjau geometri ruas jalan di peta"
                                    @click="openPreviewMap(ruas, { desa: desa.nama_desa, kecamatan: kec.nama_kecamatan })"
                                  >
                                    <UIcon name="i-lucide-map" class="w-3.5 h-3.5" />
                                    <span>Peta</span>
                                  </button>

                                  <button
                                    v-if="ruas.jumlah_segmen > 0"
                                    type="button"
                                    class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-xl bg-[#EAEFE9] dark:bg-white/[0.06] text-slate-700 dark:text-slate-200 hover:bg-slate-900 hover:text-white transition-colors cursor-pointer"
                                    title="Tampilkan segmen pembangunan"
                                    @click="toggleRuas(ruas)"
                                  >
                                    <UIcon
                                      :name="expandedRuas.has(ruas.id) ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
                                      class="w-3.5 h-3.5"
                                    />
                                  </button>
                                </div>
                              </td>
                            </tr>

                            <!-- LEVEL 4: BARIS SEGMEN FISIK HASIL PEMBANGUNAN -->
                            <template v-if="expandedRuas.has(ruas.id)">
                              <tr v-if="loadingSegmenMap[ruas.id]" class="bg-[#F4F7F3] dark:bg-[#04070d]">
                                <td colspan="8" class="py-2.5 px-20 text-slate-500">
                                  <div class="flex items-center gap-2">
                                    <UIcon name="i-lucide-loader-2" class="w-3.5 h-3.5 animate-spin text-amber-500" />
                                    <span>Mengambil rincian segmen fisik pembangunan...</span>
                                  </div>
                                </td>
                              </tr>

                              <tr
                                v-else-if="!segmenDataMap[ruas.id] || segmenDataMap[ruas.id]?.length === 0"
                                class="bg-[#F4F7F3] dark:bg-[#04070d]"
                              >
                                <td colspan="8" class="py-2.5 px-20 text-slate-400 text-[11px]">
                                  Belum ada rincian segmen fisik tercatat untuk ruas ini.
                                </td>
                              </tr>

                              <template v-else v-for="(seg, sIdx) in segmenDataMap[ruas.id]" :key="seg.id">
                                <tr class="bg-[#F4F7F3]/90 dark:bg-[#04070d] hover:bg-[#EAEFE9]/70 dark:hover:bg-white/[0.03] transition-colors text-[11px]">
                                  <td class="py-2.5 px-4 text-center text-slate-400 tabular-nums">
                                    {{ sIdx + 1 }}
                                  </td>

                                  <td class="py-2.5 px-4">
                                    <div class="ps-10 border-s-2 border-amber-500/70 space-y-0.5">
                                      <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-bold text-slate-700 dark:text-slate-300 tabular-nums">
                                          Segmen #{{ sIdx + 1 }}
                                        </span>
                                        <span
                                          v-if="seg.tahun"
                                          class="px-2 py-0.5 rounded bg-white dark:bg-white/[0.06] text-slate-700 dark:text-slate-300 font-semibold tabular-nums text-[10px]"
                                        >
                                          T.A. {{ seg.tahun }}
                                        </span>
                                        <span
                                          v-if="seg.sumber_dana"
                                          class="px-2 py-0.5 rounded bg-emerald-500/12 text-emerald-800 dark:text-emerald-300 font-semibold text-[10px]"
                                        >
                                          {{ seg.sumber_dana }}
                                        </span>
                                      </div>
                                      <p v-if="seg.keterangan" class="text-slate-500 dark:text-slate-400 line-clamp-1">
                                        {{ seg.keterangan }}
                                      </p>
                                    </div>
                                  </td>

                                  <td class="py-2.5 px-4 text-center tabular-nums text-slate-600 dark:text-slate-300">
                                    Lebar: {{ seg.lebar }} m
                                  </td>

                                  <td class="py-2.5 px-4 text-right tabular-nums font-semibold text-slate-800 dark:text-slate-200">
                                    {{ formatMeterOrKm(seg.panjang) }}
                                  </td>

                                  <td class="py-2.5 px-4 text-right tabular-nums font-semibold text-emerald-700 dark:text-emerald-400">
                                    {{ formatMeterOrKm(seg.panjang) }}
                                  </td>

                                  <td class="py-2.5 px-4 text-right text-slate-400">-</td>

                                  <td class="py-2.5 px-4">
                                    <span
                                      :class="[
                                        'inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold capitalize',
                                        getKondisiBadgeClass(seg.kondisi),
                                      ]"
                                    >
                                      {{ seg.kondisi }}
                                    </span>
                                  </td>

                                  <td class="py-2.5 px-4 text-center">
                                    <button
                                      v-if="seg.geom"
                                      type="button"
                                      class="min-h-[44px] inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white dark:bg-white/[0.06] hover:bg-emerald-700 hover:text-white text-[11px] font-semibold text-slate-700 dark:text-slate-200 transition-colors cursor-pointer"
                                      @click="
                                        openPreviewMap(seg, {
                                          desa: desa.nama_desa,
                                          kecamatan: kec.nama_kecamatan,
                                          nama_ruas: ruas.nama_ruas,
                                          kode_ruas: ruas.kode_ruas,
                                        })
                                      "
                                    >
                                      <UIcon name="i-lucide-map-pin" class="w-3.5 h-3.5" />
                                      <span>Segmen</span>
                                    </button>
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

                <!-- Table Footer: Aggregate Totals Row -->
                <tfoot v-if="filteredKecamatanList.length > 0">
                  <tr class="bg-[#EAEFE9] dark:bg-[#101c2e] text-slate-900 dark:text-white font-bold text-xs border-t-2 border-[#D5DFD3] dark:border-white/10">
                    <td colspan="2" class="py-4 px-4 uppercase tracking-wider">
                      Total Agregat ({{ summaryTotals.totalKecamatan }} Kecamatan)
                    </td>
                    <td class="py-4 px-4 text-center tabular-nums">
                      {{ formatNumber(summaryTotals.totalDesa) }} desa · {{ formatNumber(summaryTotals.totalRuas) }} ruas
                    </td>
                    <td class="py-4 px-4 text-right tabular-nums">
                      {{ formatKm(summaryTotals.totalPanjangMeter) }}
                    </td>
                    <td class="py-4 px-4 text-right tabular-nums text-emerald-700 dark:text-emerald-400">
                      {{ formatKm(summaryTotals.totalPanjangDibangun) }}
                    </td>
                    <td class="py-4 px-4 text-right tabular-nums text-orange-600 dark:text-orange-400">
                      {{ formatKm(summaryTotals.totalPanjangBelum) }}
                    </td>
                    <td class="py-4 px-4">
                      <div class="flex items-center gap-2.5">
                        <div class="flex-1 bg-white dark:bg-white/10 h-2 rounded-full overflow-hidden">
                          <div
                            class="h-full rounded-full bg-emerald-600 dark:bg-emerald-400"
                            :style="{ width: `${summaryTotals.rataRataProgress}%` }"
                          />
                        </div>
                        <span class="tabular-nums font-bold w-14 text-right">
                          {{ formatPercent(summaryTotals.rataRataProgress) }}
                        </span>
                      </div>
                    </td>
                    <td class="py-4 px-4 text-center text-[11px] text-slate-600 dark:text-slate-300 tabular-nums">
                      {{ formatNumber(summaryTotals.totalSegmen) }} segmen
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </section>

      </div>
    </main>

    <!-- Spatial Map Preview Modal (Reuse ModalPreviewPeta with z-[110] above Navbar) -->
    <ModalPreviewPeta
      v-model:open="previewModalOpen"
      :item="activePreviewItem"
    />
  </div>
</template>

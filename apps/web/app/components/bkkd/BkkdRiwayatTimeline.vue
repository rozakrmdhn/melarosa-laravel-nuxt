<script setup lang="ts">
import type { BkkdProposal, BkkdRiwayat } from '~/types/bkkd';

interface Props {
  riwayatList?: BkkdRiwayat[];
  loading?: boolean;
  proposal?: BkkdProposal | null;
}

const props = withDefaults(defineProps<Props>(), {
  riwayatList: () => [],
  loading: false,
  proposal: null,
});

// Urutan tampilan: 'desc' (terbaru di atas) atau 'asc' (kronologis awal ke akhir)
const sortOrder = ref<'desc' | 'asc'>('desc');

// Mode lanjutan: menyembunyikan detail kolom audit secara default agar tidak teknis
const showTechnicalDetails = ref(false);

// Format mata uang rupiah
function formatCurrency(val?: number | null): string {
  if (val === null || val === undefined) return '-';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val);
}

// Format tanggal lengkap ramah orang awam
function formatDateTime(iso?: string | null): string {
  if (!iso) return '-';
  const d = new Date(iso);
  return d.toLocaleString('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  });
}

// Format waktu relatif (misal: Baru saja, 5 menit lalu, Hari ini, Kemarin)
function formatRelativeTime(iso?: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  const now = new Date();
  const diffMs = now.getTime() - d.getTime();

  // Jika baru terjadi dalam 60 detik (atau sedikit perbedaan detik server & client)
  if (diffMs <= 60000 && diffMs >= -60000) return 'Baru saja';

  const diffSec = Math.floor(diffMs / 1000);
  const diffMin = Math.floor(diffSec / 60);
  const diffHour = Math.floor(diffMin / 60);
  const diffDay = Math.floor(diffHour / 24);

  if (diffMin < 60 && diffMin > 0) {
    return `${diffMin} menit yang lalu`;
  }
  if (diffHour < 24 && diffHour > 0) {
    if (diffHour === 1) return '1 jam yang lalu';
    return `Hari ini (${diffHour} jam yang lalu)`;
  }
  if (diffDay === 1) return 'Kemarin';
  if (diffDay < 7) return `${diffDay} hari yang lalu`;
  if (diffDay < 30) return `${Math.floor(diffDay / 7)} minggu yang lalu`;
  return '';
}

// Format nilai kolom untuk mode teknis audit
function formatRawValue(val: any): string {
  if (val === null || val === undefined) return '(kosong)';
  if (typeof val === 'number') {
    if (val > 100000) return formatCurrency(val);
    return String(val);
  }
  if (typeof val === 'boolean') return val ? 'Ya' : 'Tidak';
  if (typeof val === 'object') return JSON.stringify(val);
  return String(val);
}

// Format label kolom database menjadi label yang dimengerti
function getFieldLabel(field: string): string {
  const map: Record<string, string> = {
    nilai_usulan: 'Nilai Usulan Anggaran',
    panjang: 'Panjang Kegiatan (m)',
    lebar: 'Lebar Kegiatan (m)',
    tinggi: 'Tinggi / Tebal Kegiatan (m)',
    volume: 'Target Volume',
    volume_target: 'Target Volume',
    satuan: 'Satuan Target',
    satuan_target: 'Satuan Target',
    judul_kegiatan: 'Judul Kegiatan',
    uraian_kegiatan: 'Deskripsi / Uraian Pekerjaan',
    lokasi_kegiatan: 'Lokasi Pelaksanaan',
    latitude: 'Titik Garis Lintang (Latitude)',
    longitude: 'Titik Garis Bujur (Longitude)',
    id_bidang: 'Bidang Pembangunan',
    nomor_surat_desa: 'Nomor Surat Permohonan Desa',
    tanggal_surat_desa: 'Tanggal Surat Desa',
    tanggal_diterima: 'Tanggal Berkas Diterima',
    status: 'Status Usulan',
    catatan_tapd: 'Catatan Pertimbangan TAPD',
    nilai_disetujui_tapd: 'Alokasi Pagu TAPD',
    tanggal_penetapan_tapd: 'Tanggal Penetapan TAPD',
    nomor_surat_hasil: 'Nomor Berita Acara Telaah',
    status_kesimpulan: 'Kesimpulan Hasil Telaah OPD',
    rekomendasi_anggaran: 'Rekomendasi Anggaran OPD',
    catatan_opd: 'Catatan Teknis OPD',
    id_opd: 'Dinas Teknis (OPD)',
  };
  return map[field] || field.replace(/_/g, ' ');
}

// ─── TAHAPAN UTAMA (MILESTONE TRACKER) ──────────────────────────────────────────
interface MilestoneStep {
  id: number;
  label: string;
  sublabel: string;
  status: 'completed' | 'current' | 'pending' | 'warning' | 'danger';
  statusText: string;
  icon: string;
}

const milestoneSteps = computed<MilestoneStep[]>(() => {
  if (!props.proposal) return [];
  const status = props.proposal.status;

  // Step 1: Pengusulan Desa
  const step1: MilestoneStep = {
    id: 1,
    label: 'Pengusulan Desa',
    sublabel: 'Draf & berkas awal disusun pihak desa',
    status: 'completed',
    statusText: 'Selesai Didaftarkan',
    icon: 'i-lucide-file-text',
  };

  // Step 2: Pengajuan ke Dinas
  let step2Status: MilestoneStep['status'] = 'pending';
  let step2Text = 'Menunggu Pengajuan';
  if (status === 'draft') {
    step2Status = 'current';
    step2Text = 'Sedang Disiapkan Desa';
  } else if (status === 'dibatalkan') {
    step2Status = 'danger';
    step2Text = 'Dibatalkan';
  } else {
    step2Status = 'completed';
    step2Text = 'Telah Diajukan ke Dinas';
  }

  const step2: MilestoneStep = {
    id: 2,
    label: 'Pengajuan ke OPD',
    sublabel: 'Penyerahan berkas ke dinas teknis',
    status: step2Status,
    statusText: step2Text,
    icon: 'i-lucide-send',
  };

  // Step 3: Telaah & Verifikasi Teknis
  let step3Status: MilestoneStep['status'] = 'pending';
  let step3Text = 'Belum Dimulai';
  if (status === 'draft') {
    step3Status = 'pending';
    step3Text = 'Menunggu Pengajuan';
  } else if (status === 'diajukan_opd') {
    step3Status = 'current';
    step3Text = 'Sedang Ditelaah Dinas Teknis';
  } else if (status === 'layak') {
    step3Status = 'completed';
    step3Text = 'Memenuhi Syarat (Layak)';
  } else if (status === 'layak_bersyarat') {
    step3Status = 'warning';
    step3Text = 'Layak Bersyarat';
  } else if (status === 'tidak_layak') {
    step3Status = 'danger';
    step3Text = 'Tidak Memenuhi Syarat';
  } else if (status === 'dikembalikan') {
    step3Status = 'warning';
    step3Text = 'Dikembalikan untuk Revisi';
  } else if (status === 'ditetapkan_tapd') {
    step3Status = 'completed';
    step3Text = 'Lolos Verifikasi OPD';
  } else if (status === 'dibatalkan') {
    step3Status = 'danger';
    step3Text = 'Dibatalkan';
  }

  const step3: MilestoneStep = {
    id: 3,
    label: 'Telaah Teknis OPD',
    sublabel: 'Verifikasi kelayakan & survei lapangan',
    status: step3Status,
    statusText: step3Text,
    icon: 'i-lucide-clipboard-check',
  };

  // Step 4: Keputusan & Pagu TAPD
  let step4Status: MilestoneStep['status'] = 'pending';
  let step4Text = 'Menunggu Sidang TAPD';
  if (status === 'ditetapkan_tapd') {
    step4Status = 'completed';
    step4Text = props.proposal.tahap_anggaran === 'perubahan' ? 'Resmi Masuk P-APBD' : 'Resmi Masuk APBD';
  } else if (status === 'digantikan_papbd') {
    step4Status = 'warning';
    step4Text = 'Dialihkan ke P-APBD';
  } else if (status === 'layak' || status === 'layak_bersyarat') {
    step4Status = 'current';
    step4Text = 'Antrean Prioritas TAPD';
  } else if (status === 'tidak_layak' || status === 'dikembalikan' || status === 'dibatalkan') {
    step4Status = 'danger';
    step4Text = 'Tidak Dilanjutkan';
  } else {
    step4Status = 'pending';
    step4Text = 'Tahap Akhir';
  }

  const step4: MilestoneStep = {
    id: 4,
    label: 'Penetapan TAPD',
    sublabel: 'Alokasi pagu anggaran masuk APBD',
    status: step4Status,
    statusText: step4Text,
    icon: 'i-lucide-award',
  };

  return [step1, step2, step3, step4];
});

// Penjelasan Ramah Status Terkini
const currentStatusExplanation = computed(() => {
  if (!props.proposal) return null;
  const status = props.proposal.status;

  switch (status) {
    case 'draft':
      return {
        title: 'Status: Draf Usulan Desa',
        badge: { label: 'Draf Persiapan', color: 'neutral' as const },
        description:
          'Proposal masih berada pada tahap draf persiapan oleh pihak desa. Anda dapat melengkapi informasi teknis, foto kondisi 0%, dan berkas RAB sebelum resmi diajukan.',
        nextStep: 'Jika berkas sudah lengkap, klik tombol "Ajukan ke OPD" agar proposal diteruskan ke dinas teknis.',
        bannerClass: 'border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-900/40 text-gray-700 dark:text-gray-300',
        icon: 'i-lucide-file-edit',
      };
    case 'diajukan_opd':
      return {
        title: 'Status: Sedang Ditelaah Dinas Teknis (OPD)',
        badge: { label: 'Sedang Ditelaah', color: 'info' as const },
        description:
          'Proposal telah resmi diajukan dan sedang dalam antrean evaluasi oleh dinas teknis terkait. Tim dinas akan meneliti kelayakan RAB serta kesesuaian fisik lapangan.',
        nextStep: 'Menunggu penerbitan Berita Acara Rekomendasi Hasil Telaah dari dinas teknis.',
        bannerClass: 'border-blue-200 dark:border-blue-900/50 bg-blue-50/60 dark:bg-blue-950/20 text-blue-900 dark:text-blue-200',
        icon: 'i-lucide-loader-2',
      };
    case 'layak':
      return {
        title: 'Status: Usulan Dinyatakan Layak Teknis',
        badge: { label: 'Memenuhi Syarat (Layak)', color: 'success' as const },
        description:
          'Kabar baik! Dinas teknis telah memeriksa usulan dan menyatakan telah MEMENUHI SYARAT TEKNIS (Layak). Rekomendasi pagu telah disiapkan untuk pembahasan TAPD.',
        nextStep: 'Proposal masuk antrean sidang prioritas Tim Anggaran Pemerintah Daerah (TAPD) untuk alokasi APBD.',
        bannerClass: 'border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/60 dark:bg-emerald-950/20 text-emerald-900 dark:text-emerald-200',
        icon: 'i-lucide-check-circle-2',
      };
    case 'layak_bersyarat':
      return {
        title: 'Status: Usulan Layak dengan Catatan Khusus',
        badge: { label: 'Layak Bersyarat', color: 'warning' as const },
        description:
          'Usulan dinilai LAYAK DENGAN CATATAN TEKNIS. Pihak dinas menyetujui usulan untuk dipertimbangkan dengan syarat penyempurnaan dokumen atau penyesuaian saat pengerjaan.',
        nextStep: 'Harap perhatikan catatan teknis pada tab verifikasi sebelum pelaksanaan dimulai.',
        bannerClass: 'border-amber-200 dark:border-amber-900/50 bg-amber-50/60 dark:bg-amber-950/20 text-amber-900 dark:text-amber-200',
        icon: 'i-lucide-alert-triangle',
      };
    case 'tidak_layak':
      return {
        title: 'Status: Usulan Tidak Memenuhi Syarat Teknis',
        badge: { label: 'Tidak Layak', color: 'error' as const },
        description:
          'Setelah dilakukan evaluasi dan pemeriksaan lapangan oleh dinas teknis, usulan ini dinyatakan BELUM MEMENUHI KRITERIA TEKNIS atau regulasi BKKD yang berlaku.',
        nextStep: 'Usulan tidak dilanjutkan ke tahap penetapan anggaran TAPD tahun ini.',
        bannerClass: 'border-rose-200 dark:border-rose-900/50 bg-rose-50/60 dark:bg-rose-950/20 text-rose-900 dark:text-rose-200',
        icon: 'i-lucide-x-circle',
      };
    case 'dikembalikan':
      return {
        title: 'Status: Berkas Usulan Dikembalikan',
        badge: { label: 'Perlu Revisi Dokumen', color: 'warning' as const },
        description:
          'Berkas proposal DIKEMBALIKAN kepada pengusul karena terdapat kekurangan berkas administrasi atau RAB yang perlu disempurnakan terlebih dahulu.',
        nextStep: 'Silakan perbaiki berkas sesuai catatan pemeriksa, kemudian ajukan kembali.',
        bannerClass: 'border-amber-200 dark:border-amber-900/50 bg-amber-50/60 dark:bg-amber-950/20 text-amber-900 dark:text-amber-200',
        icon: 'i-lucide-corner-down-left',
      };
    case 'ditetapkan_tapd':
      return {
        title: 'Status: RESMI DISETUJUI & DITETAPKAN TAPD',
        badge: { label: 'Disetujui Masuk APBD', color: 'success' as const },
        description:
          'Selamat! Usulan kegiatan ini telah RESMI DISETUJUI oleh Tim Anggaran Pemerintah Daerah (TAPD) Kabupaten Bojonegoro dan dialokasikan ke dalam APBD.',
        nextStep: 'Usulan telah memiliki pagu definitif dan siap diproses ke tahap pencairan/pelaksanaan kegiatan.',
        bannerClass: 'border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/30 text-emerald-900 dark:text-emerald-100',
        icon: 'i-lucide-award',
      };
    case 'digantikan_papbd':
      return {
        title: 'Status: Usulan Telah Digantikan di P-APBD',
        badge: { label: 'Digantikan di P-APBD', color: 'warning' as const },
        description:
          'Usulan ini semula telah masuk dalam penetapan RAPBD Induk, namun atas permohonan desa atau pertimbangan teknis resmi digantikan dengan usulan kegiatan baru pada Perubahan APBD (P-APBD).',
        nextStep: 'Data usulan lama tetap tersimpan utuh sebagai arsip penetapan awal dan tersandingkan dengan usulan pengganti.',
        bannerClass: 'border-amber-300 dark:border-amber-800 bg-amber-50/70 dark:bg-amber-950/30 text-amber-900 dark:text-amber-100',
        icon: 'i-lucide-arrow-right-left',
      };
    case 'dibatalkan':
      return {
        title: 'Status: Pengajuan Usulan Dibatalkan',
        badge: { label: 'Dibatalkan', color: 'error' as const },
        description:
          'Pengajuan proposal ini telah resmi dibatalkan dari proses perencanaan bantuan keuangan khusus desa.',
        nextStep: 'Data tersimpan sebagai arsip histori pembatalan.',
        bannerClass: 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 text-gray-700 dark:text-gray-300',
        icon: 'i-lucide-ban',
      };
    default:
      return null;
  }
});

// ─── DESKRIPTIF ITEM LINIMASA UNTUK ORANG AWAM ─────────────────────────────────
interface DescriptiveNarrative {
  title: string;
  category: string;
  color: 'primary' | 'success' | 'warning' | 'error' | 'info' | 'neutral';
  icon: string;
  summary: string;
  quote?: string | null;
  highlights?: Array<{ label: string; value: string }>;
  diffCount: number;
}

function getDescriptiveItem(item: BkkdRiwayat): DescriptiveNarrative {
  const aksi = item.aksi;
  const quote = item.alasan_perubahan || null;
  const diffCount = item.field_berubah?.length || 0;
  const sesudah = item.data_sesudah || {};
  const sebelum = item.data_sebelum || {};

  // 1. Usulan Baru Dibuat
  if (aksi === 'created') {
    const highlights: Array<{ label: string; value: string }> = [];
    if (sesudah.nilai_usulan) {
      highlights.push({ label: 'Estimasi Nilai Usulan', value: formatCurrency(sesudah.nilai_usulan) });
    }
    if (sesudah.panjang) {
      highlights.push({ label: 'Target Panjang Fisik', value: `${sesudah.panjang} meter` });
    }
    return {
      title: 'Usulan BKKD Pertama Kali Didaftarkan',
      category: 'Pendaftaran Usulan',
      color: 'primary',
      icon: 'i-lucide-file-plus',
      summary: `Proposal usulan resmi dicatat ke dalam sistem dengan nomor registrasi ${item.proposal_nomor || '(Baru)'}. Berkas awal mulai disusun untuk wilayah Desa ${item.nama_desa || ''}.`,
      quote,
      highlights: highlights.length > 0 ? highlights : undefined,
      diffCount,
    };
  }

  // 2. Perubahan Status Usulan
  if (aksi === 'status_changed') {
    const targetStatus = sesudah.status || item.alasan_perubahan || '';

    if (targetStatus.includes('diajukan') || targetStatus === 'diajukan_opd') {
      return {
        title: 'Proposal Resmi Diajukan ke Dinas Teknis (OPD)',
        category: 'Pengajuan Dinas',
        color: 'info',
        icon: 'i-lucide-send',
        summary: 'Pihak pengusul resmi mengajukan berkas proposal ke dinas teknis terkait untuk diteliti kelayakan teknis dan survei lapangan.',
        quote,
        diffCount,
      };
    }
    if (targetStatus === 'layak') {
      return {
        title: 'Hasil Telaah Teknis: Usulan Dinyatakan Layak',
        category: 'Memenuhi Syarat (Layak)',
        color: 'success',
        icon: 'i-lucide-check-circle-2',
        summary: 'Dinas teknis telah memeriksa proposal dan menyatakan telah memenuhi seluruh kriteria kelayakan teknis untuk dilanjutkan ke tahap TAPD.',
        quote,
        diffCount,
      };
    }
    if (targetStatus === 'layak_bersyarat') {
      return {
        title: 'Hasil Telaah Teknis: Layak dengan Catatan Khusus',
        category: 'Layak Bersyarat',
        color: 'warning',
        icon: 'i-lucide-alert-triangle',
        summary: 'Usulan dinilai layak dipertimbangkan, dengan catatan penyempurnaan dokumen atau penyesuaian teknis sebelum pekerjaan fisik direalisasikan.',
        quote,
        diffCount,
      };
    }
    if (targetStatus === 'tidak_layak') {
      return {
        title: 'Hasil Telaah Teknis: Usulan Dinyatakan Tidak Layak',
        category: 'Tidak Layak',
        color: 'error',
        icon: 'i-lucide-x-circle',
        summary: 'Berdasarkan evaluasi dinas teknis, usulan dinyatakan belum memenuhi persyaratan atau kriteria prioritas program bantuan keuangan desa.',
        quote,
        diffCount,
      };
    }
    if (targetStatus === 'dikembalikan') {
      return {
        title: 'Berkas Usulan Dikembalikan kepada Desa',
        category: 'Perlu Revisi',
        color: 'warning',
        icon: 'i-lucide-corner-down-left',
        summary: 'Dokumen usulan dikembalikan ke pihak desa untuk dilengkapi atau diperbaiki sesuai arahan tim verifikator dinas.',
        quote,
        diffCount,
      };
    }
    if (targetStatus === 'ditetapkan_tapd') {
      return {
        title: 'Usulan Ditetapkan Masuk Alokasi Anggaran TAPD',
        category: 'Disetujui Masuk APBD',
        color: 'success',
        icon: 'i-lucide-award',
        summary: 'Tim Anggaran Pemerintah Daerah (TAPD) menetapkan usulan ini untuk mendapatkan alokasi anggaran definitif dalam Rancangan APBD.',
        quote,
        diffCount,
      };
    }
    if (targetStatus === 'dibatalkan') {
      return {
        title: 'Pengajuan Usulan Dibatalkan',
        category: 'Usulan Dibatalkan',
        color: 'error',
        icon: 'i-lucide-ban',
        summary: 'Usulan ini dibatalkan dari proses berjalan sesuai permohonan atau keputusan resmi.',
        quote,
        diffCount,
      };
    }
    if (targetStatus === 'draft') {
      return {
        title: 'Status Usulan Dikembalikan ke Draf',
        category: 'Kembali ke Draf',
        color: 'neutral',
        icon: 'i-lucide-file-edit',
        summary: 'Status usulan dikembalikan ke draf untuk perbaikan berkas administrasi sebelum diajukan kembali.',
        quote,
        diffCount,
      };
    }

    return {
      title: 'Perubahan Status Tahapan Usulan',
      category: 'Status Berubah',
      color: 'info',
      icon: 'i-lucide-git-pull-request',
      summary: `Tahapan usulan berpindah dari status "${sebelum.status || 'sebelumnya'}" menjadi "${sesudah.status || 'terbaru'}".`,
      quote,
      diffCount,
    };
  }

  // 3. Pencatatan Verifikasi OPD
  if (aksi === 'verifikasi_created') {
    const highlights: Array<{ label: string; value: string }> = [];
    if (sesudah.nomor_surat_hasil) {
      highlights.push({ label: 'No. Berita Acara', value: String(sesudah.nomor_surat_hasil) });
    }
    if (sesudah.rekomendasi_anggaran) {
      highlights.push({ label: 'Rekomendasi Anggaran', value: formatCurrency(sesudah.rekomendasi_anggaran) });
    }
    return {
      title: 'Pencatatan Hasil Telaah Teknis & Berita Acara OPD',
      category: 'Telaah Lapangan',
      color: 'info',
      icon: 'i-lucide-clipboard-check',
      summary: `Dinas teknis telah mendokumentasikan hasil evaluasi teknis dan survei lapangan untuk usulan kegiatan ini.`,
      quote,
      highlights: highlights.length > 0 ? highlights : undefined,
      diffCount,
    };
  }

  // 4. Koreksi Verifikasi OPD
  if (aksi === 'verifikasi_koreksi') {
    return {
      title: 'Ralat / Koreksi Hasil Telaah Teknis OPD',
      category: 'Koreksi Telaah',
      color: 'warning',
      icon: 'i-lucide-refresh-cw',
      summary: 'Dinas teknis memperbarui catatan teknis atau menerbitkan ralat terhadap Berita Acara telaah sebelumnya.',
      quote,
      diffCount,
    };
  }

  // 5. Penetapan TAPD
  if (aksi === 'ditetapkan_tapd') {
    const highlights: Array<{ label: string; value: string }> = [];
    if (sesudah.nilai_disetujui_tapd) {
      highlights.push({ label: 'Pagu Alokasi APBD Disetujui', value: formatCurrency(sesudah.nilai_disetujui_tapd) });
    }
    return {
      title: 'Penetapan Alokasi Pagu Anggaran oleh TAPD',
      category: 'Pagu Disetujui TAPD',
      color: 'success',
      icon: 'i-lucide-award',
      summary: 'Tim Anggaran Pemerintah Daerah (TAPD) menyetujui alokasi dana bantuan keuangan untuk usulan ini masuk ke dalam rancangan anggaran daerah.',
      quote,
      highlights: highlights.length > 0 ? highlights : undefined,
      diffCount,
    };
  }

  // 6. Pembatalan TAPD
  if (aksi === 'batalkan_tapd') {
    return {
      title: 'Pembatalan Penetapan Alokasi Anggaran TAPD',
      category: 'Pagu Dibatalkan',
      color: 'error',
      icon: 'i-lucide-x-circle',
      summary: 'Alokasi anggaran yang sebelumnya ditetapkan oleh TAPD telah dibatalkan kembali dari rancangan APBD.',
      quote,
      diffCount,
    };
  }

  // 7. Berkas Lampiran Ditambahkan
  if (aksi === 'lampiran_added') {
    return {
      title: 'Dokumen Persyaratan Baru Ditambahkan',
      category: 'Unggah Berkas',
      color: 'neutral',
      icon: 'i-lucide-paperclip',
      summary: 'Dokumen pendukung (seperti surat permohonan desa, RAB, atau foto kondisi awal 0%) telah diunggah ke sistem proposal.',
      quote,
      diffCount,
    };
  }

  // 8. Berkas Lampiran Dihapus
  if (aksi === 'lampiran_removed') {
    return {
      title: 'Dokumen Lampiran Dihapus',
      category: 'Hapus Berkas',
      color: 'neutral',
      icon: 'i-lucide-trash-2',
      summary: 'Salah satu berkas lampiran pendukung telah dihapus dari daftar dokumen kelengkapan usulan.',
      quote,
      diffCount,
    };
  }

  // 9. Soft Delete & Restore
  if (aksi === 'deleted') {
    return {
      title: 'Usulan Dihapus Sementara (Tempat Sampah)',
      category: 'Arsip Dihapus',
      color: 'error',
      icon: 'i-lucide-trash-2',
      summary: 'Data usulan dipindahkan ke dalam arsip tempat sampah dan dinonaktifkan dari proses berjalan.',
      quote,
      diffCount,
    };
  }
  if (aksi === 'restored') {
    return {
      title: 'Usulan Dipulihkan dari Tempat Sampah',
      category: 'Pemulihan Usulan',
      color: 'success',
      icon: 'i-lucide-archive-restore',
      summary: 'Data usulan berhasil dipulihkan kembali dari tempat sampah ke daftar aktif.',
      quote,
      diffCount,
    };
  }

  // 10. Pembaruan Data Usulan (Updated)
  if (aksi === 'updated') {
    const fields = item.field_berubah || [];
    const highlights: Array<{ label: string; value: string }> = [];

    // Perubahan anggaran
    if (fields.includes('nilai_usulan')) {
      const lama = sebelum.nilai_usulan !== undefined ? formatCurrency(sebelum.nilai_usulan) : '-';
      const baru = sesudah.nilai_usulan !== undefined ? formatCurrency(sesudah.nilai_usulan) : '-';
      highlights.push({ label: 'Perubahan Nilai Usulan', value: `${lama} → ${baru}` });

      return {
        title: 'Penyesuaian Nilai Usulan Anggaran',
        category: 'Perubahan Anggaran',
        color: 'info',
        icon: 'i-lucide-coins',
        summary: `Estimasi anggaran yang diajukan disesuaikan dari ${lama} menjadi ${baru}.`,
        quote,
        highlights,
        diffCount,
      };
    }

    // Perubahan dimensi fisik
    if (fields.some((f) => ['panjang', 'lebar', 'tinggi', 'volume', 'satuan'].includes(f))) {
      const parts: string[] = [];
      if (sesudah.panjang) parts.push(`Panjang ${sesudah.panjang} m`);
      if (sesudah.lebar) parts.push(`Lebar ${sesudah.lebar} m`);
      if (sesudah.tinggi) parts.push(`Tinggi/Tebal ${sesudah.tinggi} m`);
      if (sesudah.volume) parts.push(`Volume ${sesudah.volume} ${sesudah.satuan || ''}`);

      if (parts.length > 0) {
        highlights.push({ label: 'Target Dimensi Terbaru', value: parts.join(' • ') });
      }

      return {
        title: 'Penyesuaian Dimensi & Target Fisik Usulan',
        category: 'Pembaruan Dimensi',
        color: 'info',
        icon: 'i-lucide-ruler',
        summary: 'Spesifikasi ukuran teknis dan target volume pekerjaan fisik disesuaikan sesuai hasil pengukuran ulang.',
        quote,
        highlights: highlights.length > 0 ? highlights : undefined,
        diffCount,
      };
    }

    // Pembaruan lokasi / judul
    if (fields.includes('judul_kegiatan') || fields.includes('lokasi_kegiatan')) {
      return {
        title: 'Pembaruan Judul & Lokasi Kegiatan',
        category: 'Pembaruan Informasi',
        color: 'neutral',
        icon: 'i-lucide-map-pin',
        summary: `Informasi judul atau lokasi fisik kegiatan diperbarui untuk memastikan akurasi data wilayah.`,
        quote,
        diffCount,
      };
    }

    // Default update
    return {
      title: 'Pembaruan Informasi Data Usulan',
      category: 'Pembaruan Data',
      color: 'neutral',
      icon: 'i-lucide-edit-3',
      summary: `Terjadi pembaruan pada data usulan kegiatan (${fields.length} atribut diperbarui).`,
      quote,
      diffCount,
    };
  }

  // Fallback
  return {
    title: `Aktivitas Usulan: ${aksi.replace(/_/g, ' ')}`,
    category: 'Riwayat Usulan',
    color: 'neutral',
    icon: 'i-lucide-clock',
    summary: 'Aktivitas sistem tercatat pada usulan ini.',
    quote,
    diffCount,
  };
}

// Daftar terurut berdasarkan pilihan pengguna
const sortedRiwayatList = computed(() => {
  const list = [...props.riwayatList];
  return list.sort((a, b) => {
    const timeA = new Date(a.created_at || '').getTime();
    const timeB = new Date(b.created_at || '').getTime();
    return sortOrder.value === 'desc' ? timeB - timeA : timeA - timeB;
  });
});
</script>

<template>
  <div class="space-y-6">
    <!-- ═══ 1. MILESTONE STEPPER (TAHAPAN UTAMA USULAN) ═══════════════════════ -->
    <div
      v-if="proposal"
      class="p-5 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs space-y-4"
    >
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-2">
            <UIcon name="i-lucide-route" class="w-4 h-4 text-primary-500" />
            <span>Tahapan Perjalanan Usulan BKKD</span>
          </h3>
          <p class="text-xs text-gray-500 mt-0.5">
            4 tahapan standar perjalanan usulan mulai dari desa hingga penetapan APBD Kabupaten
          </p>
        </div>
        <UBadge color="neutral" variant="subtle" size="xs" class="font-mono hidden sm:inline-flex">
          T.A. {{ proposal.tahun_anggaran }}
        </UBadge>
      </div>

      <!-- Stepper Track Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-1">
        <div
          v-for="(step, idx) in milestoneSteps"
          :key="step.id"
          class="relative p-3.5 rounded-xl border transition-all duration-200 flex flex-col justify-between gap-3"
          :class="[
            step.status === 'completed'
              ? 'border-emerald-200 dark:border-emerald-900/60 bg-emerald-50/40 dark:bg-emerald-950/20'
              : step.status === 'current'
              ? 'border-primary-300 dark:border-primary-800 bg-primary-50/40 dark:bg-primary-950/20 ring-1 ring-primary-500/30'
              : step.status === 'warning'
              ? 'border-amber-200 dark:border-amber-900/60 bg-amber-50/40 dark:bg-amber-950/20'
              : step.status === 'danger'
              ? 'border-rose-200 dark:border-rose-900/60 bg-rose-50/40 dark:bg-rose-950/20'
              : 'border-gray-200/70 dark:border-white/[0.06] bg-gray-50/40 dark:bg-gray-900/20 opacity-75',
          ]"
        >
          <!-- Step Header: Number & Status Icon -->
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <div
                class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                :class="[
                  step.status === 'completed'
                    ? 'bg-emerald-500 text-white'
                    : step.status === 'current'
                    ? 'bg-primary-600 text-white animate-pulse'
                    : step.status === 'warning'
                    ? 'bg-amber-500 text-white'
                    : step.status === 'danger'
                    ? 'bg-rose-500 text-white'
                    : 'bg-gray-200 dark:bg-gray-800 text-gray-500',
                ]"
              >
                <UIcon
                  v-if="step.status === 'completed'"
                  name="i-lucide-check"
                  class="w-3.5 h-3.5 stroke-[3]"
                />
                <span v-else>{{ idx + 1 }}</span>
              </div>
              <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">
                Tahap {{ idx + 1 }}
              </span>
            </div>

            <!-- Indicator Pill -->
            <UBadge
              :color="
                step.status === 'completed'
                  ? 'success'
                  : step.status === 'current'
                  ? 'primary'
                  : step.status === 'warning'
                  ? 'warning'
                  : step.status === 'danger'
                  ? 'error'
                  : 'neutral'
              "
              size="xs"
              :variant="step.status === 'current' ? 'solid' : 'subtle'"
              class="text-[10px]"
            >
              {{
                step.status === 'completed'
                  ? 'Selesai'
                  : step.status === 'current'
                  ? 'Aktif'
                  : step.status === 'warning'
                  ? 'Catatan'
                  : step.status === 'danger'
                  ? 'Berhenti'
                  : 'Menunggu'
              }}
            </UBadge>
          </div>

          <!-- Step Body: Label & Details -->
          <div>
            <h4
              class="text-xs font-bold truncate"
              :class="[
                step.status === 'completed'
                  ? 'text-emerald-900 dark:text-emerald-200'
                  : step.status === 'current'
                  ? 'text-primary-900 dark:text-primary-100'
                  : step.status === 'warning'
                  ? 'text-amber-900 dark:text-amber-200'
                  : step.status === 'danger'
                  ? 'text-rose-900 dark:text-rose-200'
                  : 'text-gray-600 dark:text-gray-400',
              ]"
            >
              {{ step.label }}
            </h4>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-1">
              {{ step.sublabel }}
            </p>
          </div>

          <!-- Step Footer: Status Text -->
          <div
            class="pt-2 border-t border-black/[0.04] dark:border-white/[0.04] text-[11px] font-medium flex items-center gap-1.5"
            :class="[
              step.status === 'completed'
                ? 'text-emerald-700 dark:text-emerald-400'
                : step.status === 'current'
                ? 'text-primary-700 dark:text-primary-400'
                : step.status === 'warning'
                ? 'text-amber-700 dark:text-amber-400'
                : step.status === 'danger'
                ? 'text-rose-700 dark:text-rose-400'
                : 'text-gray-400',
            ]"
          >
            <UIcon :name="step.icon" class="w-3.5 h-3.5 shrink-0" />
            <span class="truncate">{{ step.statusText }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ 2. KARTU PANDUAN STATUS TERKINI & ANGGARAN ═════════════════════════ -->
    <div
      v-if="proposal && currentStatusExplanation"
      class="p-4 sm:p-5 rounded-xl border flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4"
      :class="currentStatusExplanation.bannerClass"
    >
      <div class="flex items-start gap-3.5">
        <div class="w-10 h-10 rounded-xl bg-white/80 dark:bg-black/30 border border-current/10 flex items-center justify-center shrink-0 shadow-xs">
          <UIcon :name="currentStatusExplanation.icon" class="w-5 h-5 text-current" />
        </div>
        <div class="space-y-1">
          <div class="flex flex-wrap items-center gap-2">
            <h4 class="text-sm font-bold text-gray-900 dark:text-white">
              {{ currentStatusExplanation.title }}
            </h4>
            <UBadge
              :color="currentStatusExplanation.badge.color"
              size="xs"
              variant="solid"
              class="font-semibold"
            >
              {{ currentStatusExplanation.badge.label }}
            </UBadge>
          </div>
          <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed max-w-3xl">
            {{ currentStatusExplanation.description }}
          </p>
          <div class="pt-0.5 flex items-center gap-1.5 text-xs font-semibold text-primary-700 dark:text-primary-300">
            <UIcon name="i-lucide-arrow-right-circle" class="w-3.5 h-3.5 shrink-0" />
            <span>Langkah Berikutnya: {{ currentStatusExplanation.nextStep }}</span>
          </div>
        </div>
      </div>

      <!-- Financial Snapshot (Perjalanan Anggaran) -->
      <div class="w-full lg:w-auto shrink-0 bg-white dark:bg-[#0b0f19] p-3 rounded-lg border border-gray-200/70 dark:border-white/[0.08] shadow-xs flex items-center justify-around sm:justify-start gap-4 text-xs">
        <div class="text-center sm:text-left">
          <span class="text-[10px] text-gray-400 block uppercase font-medium">Usulan Desa</span>
          <span class="font-bold text-gray-900 dark:text-gray-100 font-mono">
            {{ proposal.nilai_usulan ? formatCurrency(proposal.nilai_usulan) : '-' }}
          </span>
        </div>
        <div class="text-gray-300 dark:text-gray-700">→</div>
        <div class="text-center sm:text-left">
          <span class="text-[10px] text-gray-400 block uppercase font-medium">Rekomendasi Dinas</span>
          <span class="font-bold text-blue-600 dark:text-blue-400 font-mono">
            {{ proposal.verifikasi_aktif?.rekomendasi_anggaran ? formatCurrency(proposal.verifikasi_aktif.rekomendasi_anggaran) : 'Menunggu' }}
          </span>
        </div>
        <div class="text-gray-300 dark:text-gray-700">→</div>
        <div class="text-center sm:text-left">
          <span class="text-[10px] text-gray-400 block uppercase font-medium">Alokasi Akhir TAPD</span>
          <span class="font-bold text-emerald-600 dark:text-emerald-400 font-mono">
            {{ proposal.nilai_disetujui_tapd ? formatCurrency(proposal.nilai_disetujui_tapd) : 'Belum Ada' }}
          </span>
        </div>
      </div>
    </div>

    <!-- ═══ 3. DAFTAR KRONOLOGI AKTIVITAS (TIMELINE STORY) ═════════════════════ -->
    <div class="p-5 rounded-xl border border-gray-100 dark:border-white/[0.06] bg-white dark:bg-gray-900/40 shadow-xs space-y-5">
      <!-- Toolbar Pengaturan Linimasa -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-gray-100 dark:border-white/[0.06]">
        <div class="flex items-center gap-2">
          <h3 class="text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300 flex items-center gap-2">
            <UIcon name="i-lucide-history" class="w-4 h-4 text-primary-500" />
            <span>Kronologi Aktivitas & Catatan Perjalanan</span>
          </h3>
          <UBadge color="neutral" variant="subtle" size="xs">
            {{ riwayatList.length }} Catatan
          </UBadge>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
          <!-- Urutan Waktu Toggle -->
          <div class="flex items-center border border-gray-200/80 dark:border-white/[0.08] rounded-lg p-0.5 text-xs bg-gray-50/50 dark:bg-gray-900/50">
            <button
              type="button"
              class="px-2.5 py-1 rounded font-medium transition-colors"
              :class="sortOrder === 'desc' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-xs' : 'text-gray-500 hover:text-gray-900'"
              @click="sortOrder = 'desc'"
            >
              Terbaru Dulu
            </button>
            <button
              type="button"
              class="px-2.5 py-1 rounded font-medium transition-colors"
              :class="sortOrder === 'asc' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-xs' : 'text-gray-500 hover:text-gray-900'"
              @click="sortOrder = 'asc'"
            >
              Terlama Dulu
            </button>
          </div>

          <!-- Toggle Mode Lanjutan (Audit Log) -->
          <UButton
            color="neutral"
            :variant="showTechnicalDetails ? 'solid' : 'ghost'"
            size="xs"
            icon="i-lucide-database"
            :label="showTechnicalDetails ? 'Sembunyikan Rincian Teknis' : 'Rincian Teknis Kolom'"
            @click="showTechnicalDetails = !showTechnicalDetails"
          />
        </div>
      </div>

      <!-- State: Loading -->
      <div v-if="loading" class="p-12 text-center text-gray-500 text-xs">
        <UIcon name="i-lucide-loader-2" class="w-7 h-7 animate-spin mx-auto text-primary-500 mb-2" />
        <p class="font-medium text-gray-700 dark:text-gray-300">Memuat catatan linimasa...</p>
        <p class="text-gray-400 mt-0.5">Menyiapkan kronologi aktivitas perjalanan usulan</p>
      </div>

      <!-- State: Kosong -->
      <div
        v-else-if="!riwayatList || riwayatList.length === 0"
        class="p-12 text-center border border-dashed border-gray-200 dark:border-white/[0.08] rounded-xl text-gray-500 text-xs space-y-2 bg-gray-50/30 dark:bg-gray-900/10"
      >
        <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-400 flex items-center justify-center mx-auto">
          <UIcon name="i-lucide-history" class="w-6 h-6" />
        </div>
        <p class="font-semibold text-gray-800 dark:text-gray-200 text-sm">
          Belum Ada Catatan Linimasa
        </p>
        <p class="text-gray-400 max-w-md mx-auto">
          Usulan ini baru saja terdaftar dan belum mengalami mutasi status, telaah teknis dinas, atau perubahan data lainnya.
        </p>
      </div>

      <!-- Timeline Feed: Descriptive & Narrative for Ordinary People -->
      <div v-else class="relative pl-6 sm:pl-8 space-y-6 before:absolute before:left-3 sm:before:left-4 before:top-3 before:bottom-3 before:w-0.5 before:bg-gray-200 dark:before:bg-gray-800">
        <div
          v-for="item in sortedRiwayatList"
          :key="item.id"
          class="relative group"
        >
          <!-- Node Icon Marker -->
          <div
            class="absolute -left-6 sm:-left-8 top-1.5 w-6 h-6 sm:w-7 sm:h-7 rounded-full bg-white dark:bg-[#0b0f19] border-2 flex items-center justify-center shrink-0 shadow-xs transition-transform group-hover:scale-110"
            :class="[
              getDescriptiveItem(item).color === 'success'
                ? 'border-emerald-500 text-emerald-600 bg-emerald-50 dark:bg-emerald-950/50'
                : getDescriptiveItem(item).color === 'primary'
                ? 'border-primary-500 text-primary-600 bg-primary-50 dark:bg-primary-950/50'
                : getDescriptiveItem(item).color === 'info'
                ? 'border-blue-500 text-blue-600 bg-blue-50 dark:bg-blue-950/50'
                : getDescriptiveItem(item).color === 'warning'
                ? 'border-amber-500 text-amber-600 bg-amber-50 dark:bg-amber-950/50'
                : getDescriptiveItem(item).color === 'error'
                ? 'border-rose-500 text-rose-600 bg-rose-50 dark:bg-rose-950/50'
                : 'border-gray-400 text-gray-500 bg-gray-50 dark:bg-gray-900',
            ]"
          >
            <UIcon :name="getDescriptiveItem(item).icon" class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
          </div>

          <!-- History Content Card -->
          <div class="p-4 sm:p-5 rounded-xl border border-gray-200/80 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] shadow-xs space-y-3 hover:border-gray-300 dark:hover:border-white/[0.14] transition-colors">
            <!-- Header Baris: Judul Manusiawi, Kategori, Waktu -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <div class="space-y-0.5">
                <div class="flex flex-wrap items-center gap-2">
                  <h4 class="text-sm font-bold text-gray-900 dark:text-white">
                    {{ getDescriptiveItem(item).title }}
                  </h4>
                  <UBadge
                    :color="getDescriptiveItem(item).color"
                    size="xs"
                    variant="subtle"
                    class="font-medium"
                  >
                    {{ getDescriptiveItem(item).category }}
                  </UBadge>
                </div>
              </div>

              <!-- Timestamp & Relative Time -->
              <div class="text-[11px] text-gray-500 dark:text-gray-400 flex items-center gap-2 shrink-0">
                <span v-if="formatRelativeTime(item.created_at)" class="font-semibold text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">
                  {{ formatRelativeTime(item.created_at) }}
                </span>
                <span>•</span>
                <span class="font-mono text-gray-500">{{ formatDateTime(item.created_at) }}</span>
              </div>
            </div>

            <!-- Narasi Deskriptif Utama -->
            <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
              {{ getDescriptiveItem(item).summary }}
            </p>

            <!-- Highlights Nilai Utama (Misal: Perubahan Anggaran / Dimensi) -->
            <div
              v-if="getDescriptiveItem(item).highlights && getDescriptiveItem(item).highlights!.length > 0"
              class="flex flex-wrap items-center gap-2 pt-1"
            >
              <div
                v-for="(hl, hIdx) in getDescriptiveItem(item).highlights"
                :key="hIdx"
                class="px-2.5 py-1.5 rounded-lg border border-gray-200/70 dark:border-white/[0.08] bg-gray-50/60 dark:bg-gray-900/40 text-xs flex items-center gap-2"
              >
                <span class="text-gray-500 text-[11px] font-medium">{{ hl.label }}:</span>
                <span class="font-bold text-gray-900 dark:text-gray-100 font-mono">{{ hl.value }}</span>
              </div>
            </div>

            <!-- Kutipan Catatan / Alasan Resmi (Jika Ada) -->
            <div
              v-if="getDescriptiveItem(item).quote"
              class="p-3 rounded-lg border text-xs flex items-start gap-2.5"
              :class="[
                getDescriptiveItem(item).color === 'warning'
                  ? 'border-amber-200/80 dark:border-amber-900/40 bg-amber-50/60 dark:bg-amber-950/20 text-amber-900 dark:text-amber-200'
                  : getDescriptiveItem(item).color === 'error'
                  ? 'border-rose-200/80 dark:border-rose-900/40 bg-rose-50/60 dark:bg-rose-950/20 text-rose-900 dark:text-rose-200'
                  : 'border-blue-100 dark:border-blue-900/40 bg-blue-50/40 dark:bg-blue-950/10 text-blue-950 dark:text-blue-200',
              ]"
            >
              <UIcon name="i-lucide-message-square" class="w-4 h-4 shrink-0 mt-0.5 opacity-70" />
              <div class="space-y-0.5">
                <span class="font-bold text-[11px] block uppercase tracking-wider opacity-80">Catatan / Alasan Resmi:</span>
                <p class="italic leading-relaxed">"{{ getDescriptiveItem(item).quote }}"</p>
              </div>
            </div>

            <!-- Footer: Pemroses / Actor -->
            <div class="pt-2 border-t border-gray-100 dark:border-white/[0.06] flex items-center justify-between text-[11px] text-gray-500">
              <div class="flex items-center gap-1.5">
                <UIcon name="i-lucide-user" class="w-3.5 h-3.5 text-gray-400" />
                <span>Diproses oleh:</span>
                <span class="font-semibold text-gray-700 dark:text-gray-300">
                  {{ item.changed_by_name || 'Sistem BKKD' }}
                </span>
                <span v-if="item.opd_nama" class="text-primary-600 dark:text-primary-400">
                  ({{ item.opd_nama }})
                </span>
              </div>

              <!-- Penanda Versi Teknis -->
              <span class="font-mono text-[10px] text-gray-400">
                v{{ item.versi }}
              </span>
            </div>

            <!-- ═══ MODE LANJUTAN: DETAIL TEKNIS AUDIT LOG (OPSIONAL) ═══════════ -->
            <div
              v-if="showTechnicalDetails && item.field_berubah && item.field_berubah.length > 0"
              class="pt-3 border-t border-dashed border-gray-200 dark:border-white/[0.08] space-y-2"
            >
              <div class="flex items-center justify-between text-[11px] text-gray-400">
                <span class="font-semibold uppercase tracking-wider">
                  Rincian Teknis Kolom Database ({{ item.field_berubah.length }} kolom):
                </span>
                <span class="font-mono text-[10px]">Aksi: {{ item.aksi }}</span>
              </div>

              <div class="divide-y divide-gray-100 dark:divide-white/[0.04] text-xs border border-gray-200/80 dark:border-white/[0.08] rounded-lg overflow-hidden bg-gray-50/40 dark:bg-gray-900/40">
                <div
                  v-for="field in item.field_berubah"
                  :key="field"
                  class="grid grid-cols-12 p-2.5 gap-2 items-center text-[11px]"
                >
                  <div class="col-span-4 font-semibold text-gray-700 dark:text-gray-300 truncate">
                    <span>{{ getFieldLabel(field) }}</span>
                    <span class="block text-[10px] font-mono text-gray-400 font-normal truncate">({{ field }})</span>
                  </div>
                  <div class="col-span-3 text-rose-600 dark:text-rose-400 line-through truncate font-mono">
                    {{ formatRawValue(item.data_sebelum?.[field]) }}
                  </div>
                  <div class="col-span-1 text-center text-gray-400">→</div>
                  <div class="col-span-4 text-emerald-600 dark:text-emerald-400 font-bold truncate font-mono">
                    {{ formatRawValue(item.data_sesudah?.[field]) }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

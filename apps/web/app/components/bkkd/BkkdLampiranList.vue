<script setup lang="ts">
import type { BkkdProposalLampiran } from '~/types/bkkd';
import { useBkkdApi } from '~/composables/useBkkdApi';

interface Props {
  proposalId: string;
  lampiranList?: BkkdProposalLampiran[];
  canManage?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  lampiranList: () => [],
  canManage: true,
});

const emit = defineEmits<{
  (e: 'refresh'): void;
}>();

const toast = useToast();
const api = useBkkdApi();

const mode = ref<'file' | 'link'>('file');
const uploading = ref(false);
const deletingId = ref<string | null>(null);

const jenisDokumen = ref<'proposal_desa' | 'rab' | 'gambar_rencana' | 'foto_kondisi_0' | 'surat_pengantar_kecamatan' | 'lainnya'>('proposal_desa');
const selectedFile = ref<File | null>(null);

// Form Tautan Cloud
const formLink = reactive({
  nama_asli: '',
  url_link: '',
});
const linkError = ref('');

const jenisOptions = [
  { label: 'Proposal Resmi Desa', value: 'proposal_desa' },
  { label: 'Rincian Anggaran Biaya (RAB)', value: 'rab' },
  { label: 'Gambar Rencana Teknis', value: 'gambar_rencana' },
  { label: 'Foto Kondisi Fisik 0%', value: 'foto_kondisi_0' },
  { label: 'Surat Pengantar Kecamatan', value: 'surat_pengantar_kecamatan' },
  { label: 'Dokumen Pendukung Lainnya', value: 'lainnya' },
];

function formatBytes(bytes?: number | null) {
  if (!bytes || bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function handleFileSelect(e: Event) {
  const target = e.target as HTMLInputElement;
  if (target.files && target.files.length > 0) {
    selectedFile.value = target.files[0];
  }
}

async function handleUploadFile() {
  if (!selectedFile.value) {
    toast.add({
      icon: 'i-lucide-alert-triangle',
      title: 'Pilih Berkas',
      description: 'Silakan pilih file dokumen terlebih dahulu.',
      color: 'warning',
    });
    return;
  }

  uploading.value = true;
  try {
    await api.uploadLampiran(props.proposalId, selectedFile.value, jenisDokumen.value);
    toast.add({
      icon: 'i-lucide-check-circle',
      title: 'Lampiran Berhasil Diunggah',
      description: `Berkas '${selectedFile.value.name}' berhasil ditambahkan.`,
      color: 'success',
    });

    selectedFile.value = null;
    emit('refresh');
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Mengunggah',
      description: err?.data?.message || 'Gagal menyimpan lampiran berkas.',
      color: 'error',
    });
  } finally {
    uploading.value = false;
  }
}

function detectCloudProvider(url?: string | null): { name: string; icon: string; badgeColor: 'warning' | 'info' | 'primary' | 'neutral' } {
  if (!url) return { name: 'Tautan Cloud', icon: 'i-lucide-link-2', badgeColor: 'neutral' };
  const lower = url.toLowerCase();
  if (lower.includes('drive.google.com') || lower.includes('docs.google.com')) {
    return { name: 'Google Drive', icon: 'i-lucide-hard-drive', badgeColor: 'warning' };
  }
  if (lower.includes('1drv.ms') || lower.includes('onedrive.live.com') || lower.includes('sharepoint.com')) {
    return { name: 'OneDrive', icon: 'i-lucide-cloud', badgeColor: 'info' };
  }
  if (lower.includes('dropbox.com')) {
    return { name: 'Dropbox', icon: 'i-lucide-box', badgeColor: 'primary' };
  }
  return { name: 'Tautan Cloud', icon: 'i-lucide-link-2', badgeColor: 'neutral' };
}

async function handleSimpanLink() {
  linkError.value = '';
  const url = formLink.url_link.trim();
  if (!url) {
    linkError.value = 'URL tautan cloud wajib diisi.';
    return;
  }

  try {
    const parsed = new URL(url);
    if (!['http:', 'https:'].includes(parsed.protocol)) {
      linkError.value = 'URL tautan harus diawali dengan http:// atau https://';
      return;
    }
  } catch {
    linkError.value = 'Format URL tidak valid. Pastikan menyalin tautan lengkap dari Google Drive / OneDrive.';
    return;
  }

  uploading.value = true;
  try {
    await api.tambahLampiranLink(props.proposalId, {
      jenis_dokumen: jenisDokumen.value,
      url_link: url,
      nama_asli: formLink.nama_asli.trim() || undefined,
    });

    toast.add({
      icon: 'i-lucide-check-circle',
      title: 'Tautan Cloud Disimpan',
      description: 'Tautan dokumen lampiran berhasil ditambahkan ke proposal.',
      color: 'success',
    });

    formLink.url_link = '';
    formLink.nama_asli = '';
    emit('refresh');
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Menyimpan Tautan',
      description: err?.data?.message || 'Terjadi kesalahan saat menyimpan tautan cloud.',
      color: 'error',
    });
  } finally {
    uploading.value = false;
  }
}

async function handleDelete(item: BkkdProposalLampiran) {
  const isLink = item.tipe_sumber === 'link' || Boolean(item.url_link);
  const konfirmasiText = isLink
    ? `Hapus tautan cloud "${item.nama_asli}"?`
    : `Hapus berkas lampiran "${item.nama_asli}"?`;

  if (!confirm(konfirmasiText)) return;

  deletingId.value = item.id;
  try {
    await api.deleteLampiran(props.proposalId, item.id);
    toast.add({
      icon: 'i-lucide-check-circle',
      title: 'Lampiran Dihapus',
      description: `Lampiran '${item.nama_asli}' berhasil dihapus.`,
      color: 'success',
    });
    emit('refresh');
  } catch (err: any) {
    toast.add({
      icon: 'i-lucide-alert-circle',
      title: 'Gagal Menghapus',
      description: err?.data?.message || 'Gagal menghapus lampiran.',
      color: 'error',
    });
  } finally {
    deletingId.value = null;
  }
}

function getDownloadUrl(item: BkkdProposalLampiran) {
  if (item.tipe_sumber === 'link' && item.url_link) {
    return item.url_link;
  }
  return api.getLampiranDownloadUrl(props.proposalId, item.id);
}
</script>

<template>
  <div class="space-y-4">
    <!-- Formulir Tambah Lampiran Baru (Jika diizinkan) -->
    <div v-if="canManage" class="p-4 rounded-xl border border-gray-200 dark:border-white/[0.08] bg-gray-50/70 dark:bg-gray-900/40 space-y-3.5">
      <!-- Toggle Mode: Upload File vs Tautan Cloud -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pb-2 border-b border-gray-200/70 dark:border-white/[0.06]">
        <div class="text-xs font-semibold text-gray-800 dark:text-gray-200 flex items-center gap-1.5">
          <UIcon name="i-lucide-paperclip" class="w-4 h-4 text-primary-500" />
          <span>Tambah Lampiran Dokumen</span>
        </div>

        <!-- Segmented Tab Toggle -->
        <div class="inline-flex items-center p-0.5 rounded-lg bg-gray-200/70 dark:bg-gray-800 border border-gray-200 dark:border-white/[0.06] text-xs">
          <button
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-medium transition-all"
            :class="[
              mode === 'file'
                ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-xs font-semibold'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
            ]"
            @click="mode = 'file'"
          >
            <UIcon name="i-lucide-file-up" class="w-3.5 h-3.5" />
            <span>Unggah File</span>
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-medium transition-all"
            :class="[
              mode === 'link'
                ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-xs font-semibold'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
            ]"
            @click="mode = 'link'"
          >
            <UIcon name="i-lucide-cloud" class="w-3.5 h-3.5 text-primary-500" />
            <span>Tautan Cloud (Drive/OneDrive)</span>
          </button>
        </div>
      </div>

      <!-- Mode 1: Unggah File Lokal -->
      <div v-if="mode === 'file'" class="grid grid-cols-1 sm:grid-cols-12 gap-2.5">
        <div class="sm:col-span-4">
          <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">
            Jenis Dokumen
          </label>
          <USelectMenu
            v-model="jenisDokumen"
            :items="jenisOptions"
            value-key="value"
            label-key="label"
            class="w-full text-xs"
            size="sm"
          />
        </div>

        <div class="sm:col-span-6">
          <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">
            Pilih Berkas (PDF, XLSX, XLS, JPG, PNG maks 10MB)
          </label>
          <input
            type="file"
            accept=".pdf,.xlsx,.xls,.jpg,.jpeg,.png"
            class="w-full text-xs file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-primary-950/40 dark:file:text-primary-300 border border-gray-300 dark:border-white/10 rounded-lg p-1 bg-white dark:bg-gray-800/60"
            @change="handleFileSelect"
          />
        </div>

        <div class="sm:col-span-2 flex items-end">
          <UButton
            color="primary"
            size="sm"
            label="Unggah"
            icon="i-lucide-upload"
            :loading="uploading"
            :disabled="!selectedFile"
            class="w-full justify-center min-h-[34px]"
            @click="handleUploadFile"
          />
        </div>
      </div>

      <!-- Mode 2: Tautan Cloud (Google Drive / OneDrive) -->
      <div v-else class="space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5">
          <div class="sm:col-span-4">
            <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">
              Jenis Dokumen
            </label>
            <USelectMenu
              v-model="jenisDokumen"
              :items="jenisOptions"
              value-key="value"
              label-key="label"
              class="w-full text-xs"
              size="sm"
            />
          </div>

          <div class="sm:col-span-8">
            <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">
              Judul / Keterangan Dokumen (Opsional)
            </label>
            <UInput
              v-model="formLink.nama_asli"
              placeholder="Contoh: Folder Dokumentasi Foto 0% & Video Drone (Google Drive)"
              size="sm"
              class="w-full text-xs"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5">
          <div class="sm:col-span-10">
            <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">
              Tautan URL Cloud (Google Drive / OneDrive / Dropbox / Lainnya)
            </label>
            <div class="relative">
              <UInput
                v-model="formLink.url_link"
                placeholder="https://drive.google.com/drive/folders/... atau https://1drv.ms/..."
                icon="i-lucide-link"
                size="sm"
                class="w-full text-xs font-mono"
              />
              <div v-if="formLink.url_link" class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none">
                <UBadge
                  :color="detectCloudProvider(formLink.url_link).badgeColor"
                  size="xs"
                  variant="subtle"
                  class="text-[10px]"
                >
                  {{ detectCloudProvider(formLink.url_link).name }}
                </UBadge>
              </div>
            </div>
            <p v-if="linkError" class="text-[11px] text-rose-600 dark:text-rose-400 mt-1">
              {{ linkError }}
            </p>
          </div>

          <div class="sm:col-span-2 flex items-end">
            <UButton
              color="primary"
              size="sm"
              label="Simpan Tautan"
              icon="i-lucide-cloud"
              :loading="uploading"
              :disabled="!formLink.url_link.trim()"
              class="w-full justify-center min-h-[34px]"
              @click="handleSimpanLink"
            />
          </div>
        </div>

        <!-- Edukasi / Petunjuk Akses Cloud -->
        <div class="p-2.5 rounded-lg bg-blue-50/70 dark:bg-blue-950/30 border border-blue-200/70 dark:border-blue-900/40 flex items-start gap-2 text-[11px] text-blue-800 dark:text-blue-300">
          <UIcon name="i-lucide-info" class="w-4 h-4 shrink-0 text-blue-600 dark:text-blue-400 mt-0.5" />
          <p class="leading-relaxed">
            <strong>Tips Akses Berkas:</strong> Pastikan tautan Google Drive atau OneDrive disetel dengan hak akses <em>"Siapa saja yang memiliki link dapat melihat" (Anyone with the link can view)</em> agar tim penelaah OPD teknis dan TAPD dapat membukanya tanpa meminta izin akses tambahan.
          </p>
        </div>
      </div>
    </div>

    <!-- Daftar Berkas & Tautan Terlampir -->
    <div v-if="lampiranList && lampiranList.length > 0" class="divide-y divide-gray-100 dark:divide-white/[0.04] border border-gray-200 dark:border-white/[0.06] rounded-xl overflow-hidden bg-white dark:bg-gray-900/40 shadow-xs">
      <div
        v-for="item in lampiranList"
        :key="item.id"
        class="p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-gray-50/60 dark:hover:bg-gray-800/30 transition-colors"
      >
        <div class="flex items-start sm:items-center gap-3 min-w-0">
          <!-- Icon Penyedia / Berkas -->
          <div
            class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
            :class="[
              item.tipe_sumber === 'link' || item.url_link
                ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                : 'bg-primary-50 dark:bg-primary-950/50 text-primary-600 dark:text-primary-400'
            ]"
          >
            <UIcon
              :name="item.tipe_sumber === 'link' || item.url_link ? detectCloudProvider(item.url_link).icon : 'i-lucide-file-text'"
              class="w-5 h-5"
            />
          </div>

          <div class="min-w-0 space-y-0.5">
            <div class="flex flex-wrap items-center gap-2">
              <p class="text-xs font-semibold text-gray-900 dark:text-gray-100 truncate">
                {{ item.nama_asli }}
              </p>
              <UBadge
                v-if="item.tipe_sumber === 'link' || item.url_link"
                :color="detectCloudProvider(item.url_link).badgeColor"
                size="xs"
                variant="subtle"
                class="text-[10px]"
              >
                {{ detectCloudProvider(item.url_link).name }}
              </UBadge>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400">
              <span class="capitalize font-medium text-gray-700 dark:text-gray-300">
                {{ item.jenis_dokumen.replace(/_/g, ' ') }}
              </span>
              <span>•</span>
              <span v-if="item.tipe_sumber === 'link' || item.url_link" class="font-mono truncate max-w-xs text-primary-600 dark:text-primary-400">
                {{ item.url_link }}
              </span>
              <span v-else>
                {{ formatBytes(item.ukuran_byte) }}
              </span>
              <span v-if="item.uploaded_by_name">
                • disematkan oleh {{ item.uploaded_by_name }}
              </span>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
          <!-- Buka Tautan Cloud -->
          <a
            v-if="item.tipe_sumber === 'link' || item.url_link"
            :href="item.url_link || getDownloadUrl(item)"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg text-amber-800 dark:text-amber-200 bg-amber-100/70 dark:bg-amber-950/50 hover:bg-amber-200/70 dark:hover:bg-amber-900 transition-colors shadow-xs"
          >
            <UIcon name="i-lucide-external-link" class="w-3.5 h-3.5" />
            <span>Buka di Cloud</span>
          </a>

          <!-- Unduh Berkas Fisik -->
          <a
            v-else
            :href="getDownloadUrl(item)"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-950/50 hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors shadow-xs"
          >
            <UIcon name="i-lucide-download" class="w-3.5 h-3.5" />
            <span>Unduh Berkas</span>
          </a>

          <!-- Hapus Lampiran -->
          <UButton
            v-if="canManage"
            color="error"
            variant="ghost"
            size="xs"
            icon="i-lucide-trash-2"
            :loading="deletingId === item.id"
            aria-label="Hapus dokumen lampiran"
            @click="handleDelete(item)"
          />
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-else
      class="p-8 text-center rounded-xl border border-dashed border-gray-200 dark:border-white/[0.08] text-gray-500 space-y-2 bg-gray-50/30 dark:bg-gray-900/20"
    >
      <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto text-gray-400">
        <UIcon name="i-lucide-folder-open" class="w-5 h-5" />
      </div>
      <div>
        <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">Belum Ada Dokumen Lampiran</p>
        <p class="text-[11px] text-gray-400 mt-0.5">
          Unggah file dokumen proposal/RAB/gambar atau sematkan tautan Google Drive / OneDrive untuk melengkapi usulan ini.
        </p>
      </div>
    </div>
  </div>
</template>

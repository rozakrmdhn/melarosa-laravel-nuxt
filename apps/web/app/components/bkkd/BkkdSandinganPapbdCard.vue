<script setup lang="ts">
import type { BkkdProposal } from '~/types/bkkd';
import BkkdStatusBadge from '~/components/bkkd/BkkdStatusBadge.vue';

interface Props {
  currentProposal: BkkdProposal;
}

const props = defineProps<Props>();

// Tentukan apakah proposal yang dibuka saat ini adalah fase perubahan (P-APBD)
const isPapbd = computed(() => {
  return props.currentProposal.tahap_anggaran === 'perubahan' || Boolean(props.currentProposal.id_proposal_induk);
});

// Proposal Induk (Semula)
const proposalInduk = computed(() => {
  if (isPapbd.value) {
    return props.currentProposal.proposal_induk || null;
  }
  return props.currentProposal;
});

// Proposal P-APBD (Pengganti)
const proposalPapbd = computed(() => {
  if (isPapbd.value) {
    return props.currentProposal;
  }
  if (props.currentProposal.proposal_pengganti && props.currentProposal.proposal_pengganti.length > 0) {
    return props.currentProposal.proposal_pengganti[0];
  }
  return null;
});

// Apakah memiliki korelasi sandingan
const hasSandingan = computed(() => {
  return Boolean(proposalInduk.value && proposalPapbd.value && proposalInduk.value.id !== proposalPapbd.value.id);
});

// Deteksi perubahan substansi
const isBidangChanged = computed(() => {
  const b1 = proposalInduk.value?.id_bidang || proposalInduk.value?.nama_bidang;
  const b2 = proposalPapbd.value?.id_bidang || proposalPapbd.value?.nama_bidang;
  return Boolean(b1 && b2 && b1 !== b2);
});

const isJudulChanged = computed(() => {
  const j1 = proposalInduk.value?.judul_kegiatan?.trim().toLowerCase();
  const j2 = proposalPapbd.value?.judul_kegiatan?.trim().toLowerCase();
  return Boolean(j1 && j2 && j1 !== j2);
});

function formatCurrency(val?: number | null): string {
  if (val === null || val === undefined || isNaN(val)) return '-';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val);
}

function formatDimensi(p?: number | null, l?: number | null, t?: number | null): string {
  const parts = [];
  if (p !== null && p !== undefined) parts.push(`P: ${p}m`);
  if (l !== null && l !== undefined) parts.push(`L: ${l}m`);
  if (t !== null && t !== undefined) parts.push(`T: ${t}m`);
  return parts.length ? parts.join(' × ') : '-';
}

function formatVolume(vol?: number | null, unit?: string | null): string {
  if (vol === null || vol === undefined) return '-';
  return `${new Intl.NumberFormat('id-ID').format(vol)} ${unit || ''}`.trim();
}

// Perhitungan selisih nilai pagu TAPD
const selisihPaguTapd = computed(() => {
  const paguInduk = proposalInduk.value?.nilai_disetujui_tapd ?? null;
  const paguPapbd = proposalPapbd.value?.nilai_disetujui_tapd ?? null;
  if (paguInduk === null || paguPapbd === null) return null;
  return paguPapbd - paguInduk;
});

// Perhitungan selisih usulan desa
const selisihNilaiUsulan = computed(() => {
  const usulanInduk = proposalInduk.value?.nilai_usulan ?? null;
  const usulanPapbd = proposalPapbd.value?.nilai_usulan ?? null;
  if (usulanInduk === null || usulanPapbd === null) return null;
  return usulanPapbd - usulanInduk;
});
</script>

<template>
  <div
    v-if="hasSandingan"
    class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] shadow-xs overflow-hidden"
  >
    <!-- 1. Header Sandingan Banner -->
    <div class="p-4 sm:p-5 border-b border-gray-100 dark:border-white/[0.06] bg-gray-50/70 dark:bg-gray-800/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3.5">
      <div class="flex items-start sm:items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-primary-50 dark:bg-primary-950/50 text-primary-600 dark:text-primary-400 border border-primary-200/60 dark:border-primary-800/60 flex items-center justify-center shrink-0 shadow-2xs">
          <UIcon name="i-lucide-arrow-left-right" class="w-4 h-4" />
        </div>
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <h3 class="text-xs font-bold text-gray-900 dark:text-gray-100">
              Matriks Sandingan: Usulan Ditetapkan RAPBD vs Usulan P-APBD
            </h3>
            <UBadge color="warning" size="xs" variant="subtle" class="font-semibold text-[10px]">
              Pergantian Usulan
            </UBadge>
          </div>
          <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
            Desa {{ currentProposal.nama_desa }}, Kecamatan {{ currentProposal.nama_kecamatan }} • Tahun Anggaran {{ currentProposal.tahun_anggaran }}
          </p>
        </div>
      </div>

      <!-- Navigasi Cepat Antar Berkas -->
      <div class="flex items-center gap-2 shrink-0">
        <UButton
          v-if="isPapbd && proposalInduk?.id"
          :to="`/admin/bkkd/proposal/${proposalInduk.id}`"
          color="neutral"
          variant="outline"
          size="xs"
          icon="i-lucide-external-link"
          label="Buka Berkas RAPBD Awal"
          target="_blank"
        />
        <UButton
          v-else-if="!isPapbd && proposalPapbd?.id"
          :to="`/admin/bkkd/proposal/${proposalPapbd.id}`"
          color="neutral"
          variant="outline"
          size="xs"
          icon="i-lucide-external-link"
          label="Buka Berkas P-APBD"
          target="_blank"
        />
      </div>
    </div>

    <!-- 2. Micro KPI Strip (Delta Pagu & Usulan) -->
    <div class="p-3.5 sm:px-5 bg-gradient-to-r from-gray-50/50 via-white to-gray-50/50 dark:from-gray-900/40 dark:via-gray-900/10 dark:to-gray-900/40 border-b border-gray-100 dark:border-white/[0.06] grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs font-mono">
      <!-- Pagu Semula -->
      <div class="p-2.5 rounded-lg bg-gray-50/90 dark:bg-gray-800/40 border border-gray-200/70 dark:border-white/[0.06]">
        <div class="text-[10px] text-gray-500 font-sans font-medium flex items-center justify-between">
          <span>Pagu Ditetapkan RAPBD</span>
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" />
        </div>
        <div class="text-xs font-bold text-gray-900 dark:text-gray-100 mt-0.5">
          {{ formatCurrency(proposalInduk?.nilai_disetujui_tapd) }}
        </div>
      </div>

      <!-- Pagu Perubahan -->
      <div class="p-2.5 rounded-lg bg-gray-50/90 dark:bg-gray-800/40 border border-gray-200/70 dark:border-white/[0.06]">
        <div class="text-[10px] text-gray-500 font-sans font-medium flex items-center justify-between">
          <span>Pagu Diusulkan/Disetujui P-APBD</span>
          <span class="w-1.5 h-1.5 rounded-full bg-amber-500" />
        </div>
        <div class="text-xs font-bold text-gray-900 dark:text-gray-100 mt-0.5">
          <span v-if="proposalPapbd?.nilai_disetujui_tapd">
            {{ formatCurrency(proposalPapbd.nilai_disetujui_tapd) }}
          </span>
          <span v-else class="text-gray-400 font-sans font-normal text-[11px]">
            Menunggu Penetapan TAPD
          </span>
        </div>
      </div>

      <!-- Selisih Pagu -->
      <div
        class="p-2.5 rounded-lg border text-right sm:text-left"
        :class="[
          selisihPaguTapd !== null && selisihPaguTapd > 0
            ? 'bg-emerald-50/60 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800/50 text-emerald-700 dark:text-emerald-300'
            : selisihPaguTapd !== null && selisihPaguTapd < 0
              ? 'bg-rose-50/60 dark:bg-rose-950/30 border-rose-200 dark:border-rose-800/50 text-rose-700 dark:text-rose-300'
              : 'bg-gray-50/90 dark:bg-gray-800/40 border-gray-200/70 dark:border-white/[0.06] text-gray-600 dark:text-gray-400'
        ]"
      >
        <div class="text-[10px] font-sans font-medium">
          Selisih Alokasi Pagu TAPD
        </div>
        <div class="text-xs font-bold flex items-center justify-end sm:justify-start gap-1 mt-0.5">
          <UIcon
            v-if="selisihPaguTapd !== null && selisihPaguTapd > 0"
            name="i-lucide-trending-up"
            class="w-3.5 h-3.5 text-emerald-600"
          />
          <UIcon
            v-else-if="selisihPaguTapd !== null && selisihPaguTapd < 0"
            name="i-lucide-trending-down"
            class="w-3.5 h-3.5 text-rose-600"
          />
          <span v-if="selisihPaguTapd !== null && selisihPaguTapd !== 0">
            {{ selisihPaguTapd > 0 ? '+' : '' }}{{ formatCurrency(selisihPaguTapd) }}
          </span>
          <span v-else-if="selisihPaguTapd === 0">
            Tetap (Rp 0)
          </span>
          <span v-else class="font-sans font-normal text-[11px] text-gray-400">
            -
          </span>
        </div>
      </div>
    </div>

    <!-- 3. Tabel Sandingan Side-by-Side -->
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs border-collapse">
        <thead>
          <tr class="border-b border-gray-200 dark:border-white/[0.08] bg-gray-50/90 dark:bg-gray-800/60 text-[11px] font-semibold text-gray-600 dark:text-gray-400">
            <th class="py-3 px-4 w-60 sm:w-64 min-w-[220px] font-semibold text-gray-700 dark:text-gray-300">Parameter Usulan</th>
            <th class="py-3 px-4 w-1/2 border-r border-gray-200 dark:border-white/[0.08]">
              <div class="flex items-center gap-2">
                <UBadge color="neutral" size="xs" variant="outline" class="font-bold text-[10px] uppercase tracking-wider">
                  RAPBD Induk
                </UBadge>
                <span class="text-gray-900 dark:text-gray-100 font-semibold text-xs">
                  Usulan Semula (Ditetapkan)
                </span>
              </div>
            </th>
            <th class="py-3 px-4 w-1/2">
              <div class="flex items-center gap-2">
                <UBadge color="warning" size="xs" variant="subtle" class="font-bold text-[10px] uppercase tracking-wider">
                  P-APBD
                </UBadge>
                <span class="text-gray-900 dark:text-gray-100 font-semibold text-xs">
                  Usulan Menjadi (Perubahan)
                </span>
              </div>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04] text-gray-800 dark:text-gray-200">
          <!-- 1. Nomor Surat Desa -->
          <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition-colors">
            <td class="py-3 px-4 font-medium text-gray-600 dark:text-gray-300 whitespace-nowrap">No. Surat Desa</td>
            <td class="py-3 px-4 border-r border-gray-200 dark:border-white/[0.08]">
              <div class="font-mono font-semibold text-gray-900 dark:text-gray-100">
                {{ proposalInduk?.nomor_surat_desa || '-' }}
              </div>
              <div v-if="proposalInduk?.tanggal_surat_desa" class="text-[10px] text-gray-400 font-sans mt-0.5">
                Tanggal Surat: {{ proposalInduk.tanggal_surat_desa }}
              </div>
            </td>
            <td class="py-3 px-4">
              <div class="font-mono font-semibold text-gray-900 dark:text-gray-100">
                {{ proposalPapbd?.nomor_surat_desa || '-' }}
              </div>
              <div v-if="proposalPapbd?.tanggal_surat_desa" class="text-[10px] text-gray-400 font-sans mt-0.5">
                Tanggal Surat: {{ proposalPapbd.tanggal_surat_desa }}
              </div>
            </td>
          </tr>

          <!-- 2. Nomor Registrasi Sistem -->
          <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition-colors">
            <td class="py-3 px-4 font-medium text-gray-600 dark:text-gray-300 whitespace-nowrap">No. Registrasi Sistem</td>
            <td class="py-3 px-4 border-r border-gray-200 dark:border-white/[0.08]">
              <NuxtLink
                v-if="proposalInduk?.id"
                :to="`/admin/bkkd/proposal/${proposalInduk.id}`"
                target="_blank"
                class="font-mono font-semibold text-primary-600 dark:text-primary-400 hover:underline inline-flex items-center gap-1"
              >
                <span>{{ proposalInduk?.nomor_registrasi || '-' }}</span>
                <UIcon name="i-lucide-external-link" class="w-3.5 h-3.5 text-gray-400" />
              </NuxtLink>
              <span v-else class="font-mono text-gray-400">-</span>
            </td>
            <td class="py-3 px-4">
              <NuxtLink
                v-if="proposalPapbd?.id"
                :to="`/admin/bkkd/proposal/${proposalPapbd.id}`"
                target="_blank"
                class="font-mono font-semibold text-primary-600 dark:text-primary-400 hover:underline inline-flex items-center gap-1"
              >
                <span>{{ proposalPapbd?.nomor_registrasi || '-' }}</span>
                <UIcon name="i-lucide-external-link" class="w-3.5 h-3.5 text-gray-400" />
              </NuxtLink>
              <span v-else class="font-mono text-gray-400">-</span>
            </td>
          </tr>

          <!-- 3. Bidang BKKD -->
          <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition-colors">
            <td class="py-3 px-4 font-medium text-gray-600 dark:text-gray-300 whitespace-nowrap">Bidang BKKD</td>
            <td class="py-3 px-4 border-r border-gray-200 dark:border-white/[0.08]">
              <UBadge color="neutral" size="xs" variant="outline" class="font-medium text-[11px]">
                {{ proposalInduk?.nama_bidang || proposalInduk?.bidang?.nama || '-' }}
              </UBadge>
            </td>
            <td class="py-3 px-4">
              <div class="flex items-center gap-2">
                <UBadge color="primary" size="xs" variant="subtle" class="font-medium text-[11px]">
                  {{ proposalPapbd?.nama_bidang || proposalPapbd?.bidang?.nama || '-' }}
                </UBadge>
                <UBadge v-if="isBidangChanged" color="warning" size="xs" variant="solid" class="text-[9px] font-bold">
                  Beralih Bidang
                </UBadge>
              </div>
            </td>
          </tr>

          <!-- 4. Judul Usulan Kegiatan -->
          <tr
            class="transition-colors"
            :class="isJudulChanged ? 'bg-amber-50/30 dark:bg-amber-950/15' : 'hover:bg-gray-50/50 dark:hover:bg-gray-800/20'"
          >
            <td class="py-3 px-4 font-medium text-gray-600 dark:text-gray-300 whitespace-nowrap">Judul Kegiatan</td>
            <td class="py-3 px-4 border-r border-gray-200 dark:border-white/[0.08] font-medium text-xs sm:text-[13px] text-gray-900 dark:text-gray-100 leading-snug">
              {{ proposalInduk?.judul_kegiatan || '-' }}
            </td>
            <td class="py-3 px-4 font-medium text-xs sm:text-[13px] text-gray-900 dark:text-gray-100 leading-snug">
              <div class="flex items-start gap-1.5">
                <span class="flex-1">{{ proposalPapbd?.judul_kegiatan || '-' }}</span>
                <UBadge v-if="isJudulChanged" color="warning" size="xs" variant="subtle" class="text-[9px] shrink-0 font-medium">
                  Kegiatan Baru
                </UBadge>
              </div>
            </td>
          </tr>

          <!-- 5. Lokasi Pelaksanaan -->
          <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition-colors">
            <td class="py-3 px-4 font-medium text-gray-600 dark:text-gray-300 whitespace-nowrap">Lokasi Kegiatan</td>
            <td class="py-3 px-4 border-r border-gray-200 dark:border-white/[0.08] text-xs sm:text-[13px] text-gray-800 dark:text-gray-200 leading-relaxed">
              {{ proposalInduk?.lokasi_kegiatan || '-' }}
            </td>
            <td class="py-3 px-4 text-xs sm:text-[13px] text-gray-800 dark:text-gray-200 leading-relaxed">
              {{ proposalPapbd?.lokasi_kegiatan || '-' }}
            </td>
          </tr>

          <!-- 6. Dimensi & Target Fisik -->
          <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition-colors">
            <td class="py-3 px-4 font-medium text-gray-600 dark:text-gray-300 whitespace-nowrap">Dimensi & Volume</td>
            <td class="py-3 px-4 border-r border-gray-200 dark:border-white/[0.08] font-mono text-xs text-gray-800 dark:text-gray-200">
              <div>{{ formatDimensi(proposalInduk?.panjang, proposalInduk?.lebar, proposalInduk?.tinggi) }}</div>
              <div v-if="proposalInduk?.volume" class="text-gray-500 dark:text-gray-400 text-[10px] mt-0.5">
                Vol: {{ formatVolume(proposalInduk.volume, proposalInduk.satuan) }}
              </div>
            </td>
            <td class="py-3 px-4 font-mono text-xs text-gray-800 dark:text-gray-200">
              <div>{{ formatDimensi(proposalPapbd?.panjang, proposalPapbd?.lebar, proposalPapbd?.tinggi) }}</div>
              <div v-if="proposalPapbd?.volume" class="text-gray-500 dark:text-gray-400 text-[10px] mt-0.5">
                Vol: {{ formatVolume(proposalPapbd.volume, proposalPapbd.satuan) }}
              </div>
            </td>
          </tr>

          <!-- 7. Nilai Usulan Desa -->
          <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition-colors">
            <td class="py-3 px-4 font-medium text-gray-600 dark:text-gray-300 whitespace-nowrap">Nilai Usulan Desa</td>
            <td class="py-3 px-4 border-r border-gray-200 dark:border-white/[0.08] font-mono font-medium text-gray-900 dark:text-gray-100">
              {{ formatCurrency(proposalInduk?.nilai_usulan) }}
            </td>
            <td class="py-3 px-4 font-mono font-medium text-gray-900 dark:text-gray-100">
              <div class="flex items-center gap-2">
                <span>{{ formatCurrency(proposalPapbd?.nilai_usulan) }}</span>
                <span
                  v-if="selisihNilaiUsulan !== null && selisihNilaiUsulan !== 0"
                  class="text-[10px] font-sans font-semibold px-1.5 py-0.2 rounded"
                  :class="selisihNilaiUsulan > 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400'"
                >
                  {{ selisihNilaiUsulan > 0 ? '+' : '' }}{{ formatCurrency(selisihNilaiUsulan) }}
                </span>
              </div>
            </td>
          </tr>

          <!-- 8. Alokasi Pagu TAPD -->
          <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition-colors">
            <td class="py-3 px-4 font-medium text-gray-600 dark:text-gray-300 whitespace-nowrap">Alokasi Pagu TAPD</td>
            <td class="py-3 px-4 border-r border-gray-200 dark:border-white/[0.08] font-mono font-bold text-emerald-700 dark:text-emerald-400">
              {{ formatCurrency(proposalInduk?.nilai_disetujui_tapd) }}
            </td>
            <td class="py-3 px-4 font-mono font-bold">
              <div v-if="proposalPapbd?.nilai_disetujui_tapd" class="flex items-center gap-2 text-emerald-700 dark:text-emerald-400">
                <span>{{ formatCurrency(proposalPapbd.nilai_disetujui_tapd) }}</span>
                <span
                  v-if="selisihPaguTapd !== null && selisihPaguTapd !== 0"
                  class="text-[10px] font-sans font-semibold px-1.5 py-0.2 rounded"
                  :class="selisihPaguTapd > 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400'"
                >
                  {{ selisihPaguTapd > 0 ? '+' : '' }}{{ formatCurrency(selisihPaguTapd) }}
                </span>
              </div>
              <span v-else class="text-gray-400 font-sans font-normal text-[11px]">
                (Menunggu Penetapan P-APBD)
              </span>
            </td>
          </tr>

          <!-- 9. Status Usulan -->
          <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition-colors">
            <td class="py-3 px-4 font-medium text-gray-600 dark:text-gray-300 whitespace-nowrap">Status Terakhir</td>
            <td class="py-3 px-4 border-r border-gray-200 dark:border-white/[0.08]">
              <BkkdStatusBadge :status="proposalInduk?.status" size="sm" />
            </td>
            <td class="py-3 px-4">
              <BkkdStatusBadge :status="proposalPapbd?.status" size="sm" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 4. Catatan Alasan Resmi Pergantian Usulan -->
    <div
      v-if="proposalPapbd?.alasan_pergantian_papbd || currentProposal.alasan_pergantian_papbd"
      class="p-4 bg-amber-50/50 dark:bg-amber-950/20 border-t border-gray-200 dark:border-white/[0.08] text-xs text-gray-700 dark:text-gray-300 space-y-1.5"
    >
      <div class="font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-1.5 text-xs">
        <UIcon name="i-lucide-message-square-quote" class="w-4 h-4 text-amber-600 dark:text-amber-400" />
        <span>Alasan Resmi Perubahan / Pergantian Usulan P-APBD:</span>
      </div>
      <blockquote class="leading-relaxed text-gray-700 dark:text-gray-300 italic pl-5 border-l-2 border-amber-400 dark:border-amber-600 font-sans text-xs">
        "{{ proposalPapbd?.alasan_pergantian_papbd || currentProposal.alasan_pergantian_papbd }}"
      </blockquote>
    </div>
  </div>
</template>

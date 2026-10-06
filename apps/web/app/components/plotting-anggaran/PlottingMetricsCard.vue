<script setup lang="ts">
interface Props {
  totalKegiatan?: number;
  totalPagu?: number;
  totalPanjang?: number;
  loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  totalKegiatan: 0,
  totalPagu: 0,
  totalPanjang: 0,
  loading: false,
});

function formatRupiah(val: number): string {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val || 0);
}

function formatMeter(val: number): string {
  const m = Number(val || 0);
  if (m >= 1000) {
    return `${(m / 1000).toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 2 })} km`;
  }
  return `${m.toLocaleString('id-ID')} m`;
}
</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
    <!-- Card 1: Total Alokasi Pagu -->
    <div
      class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] p-4 transition-all duration-200 hover:shadow-sm"
    >
      <div class="flex items-center justify-between">
        <div class="space-y-1">
          <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Pagu Anggaran</span>
          <div v-if="loading" class="h-7 w-36 bg-gray-100 dark:bg-gray-800 rounded animate-pulse" />
          <div v-else class="text-xl font-bold tracking-tight text-gray-900 dark:text-white font-mono">
            {{ formatRupiah(totalPagu) }}
          </div>
          <p class="text-[11px] text-gray-400 dark:text-gray-500">Akumulasi seluruh paket kegiatan</p>
        </div>
        <div class="size-11 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/40 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
          <UIcon name="i-lucide-wallet" class="size-5" />
        </div>
      </div>
      <div class="absolute bottom-0 inset-x-0 h-0.5 bg-gradient-to-r from-emerald-500 to-teal-400" />
    </div>

    <!-- Card 2: Total Target Fisik (Panjang) -->
    <div
      class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] p-4 transition-all duration-200 hover:shadow-sm"
    >
      <div class="flex items-center justify-between">
        <div class="space-y-1">
          <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Target Panjang Fisik</span>
          <div v-if="loading" class="h-7 w-28 bg-gray-100 dark:bg-gray-800 rounded animate-pulse" />
          <div v-else class="text-xl font-bold tracking-tight text-gray-900 dark:text-white font-mono flex items-baseline gap-1.5">
            <span>{{ formatMeter(totalPanjang) }}</span>
            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">({{ totalPanjang.toLocaleString('id-ID') }} m)</span>
          </div>
          <p class="text-[11px] text-gray-400 dark:text-gray-500">Estimasi total output fisik jalan/saluran</p>
        </div>
        <div class="size-11 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/40 flex items-center justify-center text-blue-600 dark:text-blue-400 shrink-0">
          <UIcon name="i-lucide-route" class="size-5" />
        </div>
      </div>
      <div class="absolute bottom-0 inset-x-0 h-0.5 bg-gradient-to-r from-blue-500 to-cyan-400" />
    </div>

    <!-- Card 3: Total Paket Kegiatan -->
    <div
      class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] p-4 transition-all duration-200 hover:shadow-sm sm:col-span-2 lg:col-span-1"
    >
      <div class="flex items-center justify-between">
        <div class="space-y-1">
          <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Jumlah Paket Kegiatan</span>
          <div v-if="loading" class="h-7 w-20 bg-gray-100 dark:bg-gray-800 rounded animate-pulse" />
          <div v-else class="text-xl font-bold tracking-tight text-gray-900 dark:text-white font-mono">
            {{ totalKegiatan }} <span class="text-sm font-medium text-gray-500 dark:text-gray-400 font-sans">Paket</span>
          </div>
          <p class="text-[11px] text-gray-400 dark:text-gray-500">Terdaftar dalam perencanaan aktif</p>
        </div>
        <div class="size-11 rounded-xl bg-violet-50 dark:bg-violet-950/40 border border-violet-200 dark:border-violet-800/40 flex items-center justify-center text-violet-600 dark:text-violet-400 shrink-0">
          <UIcon name="i-lucide-folder-kanban" class="size-5" />
        </div>
      </div>
      <div class="absolute bottom-0 inset-x-0 h-0.5 bg-gradient-to-r from-violet-500 to-indigo-400" />
    </div>
  </div>
</template>

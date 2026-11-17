<script setup lang="ts">
import {
  formatDecimalId,
  formatIntId,
  getKondisiMeta,
  getVerifikasiMeta,
  type InfrastructureSummaryResponse,
  type JalanPorosSummaryResponse,
} from "~/types/admin-dashboard";

interface BreakdownRow {
  key: string;
  label: string;
  count: number;
  panjangKm: number;
  percentage: number;
  barClass: string;
  dotClass: string;
  badgeClass: string;
  filterQuery: Record<string, string>;
}

interface Props {
  infraSummary: InfrastructureSummaryResponse | null;
  porosSummary: JalanPorosSummaryResponse | null;
  loadingInfra: boolean;
  loadingPoros: boolean;
  infraError: string | null;
  porosError: string | null;
  selectedKondisi: string | null;
  selectedKecamatanId: number | null;
  selectedDesaId: number | null;
  canViewInfra: boolean;
  canViewPoros: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: "select-kondisi", kondisi: string | null): void;
  (e: "retry"): void;
}>();

const activeSource = ref<"infrastruktur" | "poros">(
  props.canViewInfra ? "infrastruktur" : "poros",
);
const activeDimension = ref<"kondisi" | "perkerasan" | "verifikasi">("kondisi");

watch(
  () => [props.canViewInfra, props.canViewPoros],
  ([infra, poros]) => {
    if (!infra && poros) activeSource.value = "poros";
    if (infra && !poros) activeSource.value = "infrastruktur";
  },
);

watch(activeSource, (src) => {
  if (src === "poros" && activeDimension.value === "verifikasi") {
    activeDimension.value = "kondisi";
  }
});

const PERKERASAN_TONES = [
  {
    barClass: "bg-blue-600 dark:bg-blue-500",
    dotClass: "bg-blue-600 dark:bg-blue-400",
    badgeClass:
      "bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800/70",
  },
  {
    barClass: "bg-emerald-600 dark:bg-emerald-500",
    dotClass: "bg-emerald-600 dark:bg-emerald-400",
    badgeClass:
      "bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800/70",
  },
  {
    barClass: "bg-sky-600 dark:bg-sky-500",
    dotClass: "bg-sky-600 dark:bg-sky-400",
    badgeClass:
      "bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800/70",
  },
  {
    barClass: "bg-amber-600 dark:bg-amber-500",
    dotClass: "bg-amber-600 dark:bg-amber-400",
    badgeClass:
      "bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800/70",
  },
  {
    barClass: "bg-slate-500 dark:bg-slate-400",
    dotClass: "bg-slate-500 dark:bg-slate-400",
    badgeClass:
      "bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700",
  },
];

const isLoading = computed(() =>
  activeSource.value === "infrastruktur" ? props.loadingInfra : props.loadingPoros,
);

const activeError = computed(() =>
  activeSource.value === "infrastruktur" ? props.infraError : props.porosError,
);

const totalCount = computed(() => {
  if (activeSource.value === "infrastruktur") {
    return props.infraSummary?.total_segmen ?? 0;
  }
  return props.porosSummary?.total_ruas ?? 0;
});

const totalKm = computed(() => {
  if (activeSource.value === "infrastruktur") {
    return props.infraSummary?.total_panjang_km ?? 0;
  }
  return props.porosSummary?.total_panjang_km ?? 0;
});

const questionSubtitle = computed(() => {
  if (activeDimension.value === "kondisi") {
    return activeSource.value === "infrastruktur"
      ? "Bagaimana sebaran kondisi fisik segmen jalan saat ini?"
      : "Bagaimana komposisi kondisi ruas Master Jalan Poros Desa?";
  }
  if (activeDimension.value === "perkerasan") {
    return "Material perkerasan apa yang paling dominan di wilayah terpilih?";
  }
  return "Berapa segmen yang sudah final vs menunggu verifikasi berjenjang?";
});

const rows = computed<BreakdownRow[]>(() => {
  const denom = Math.max(totalCount.value, 1);

  if (activeDimension.value === "kondisi") {
    const raw =
      activeSource.value === "infrastruktur"
        ? (props.infraSummary?.kondisi ?? [])
        : (props.porosSummary?.kondisi ?? []);

    return raw.map((item) => {
      const meta = getKondisiMeta(item.kondisi);
      const count = Number(item.jumlah ?? 0);
      const meter = Number(item.panjang_meter ?? 0);
      const km =
        item.panjang_km !== undefined
          ? Number(item.panjang_km)
          : Math.round((meter / 1000) * 100) / 100;

      return {
        key: item.kondisi,
        label: meta.label,
        count,
        panjangKm: km,
        percentage: Math.round((count / denom) * 1000) / 10,
        barClass: meta.barClass,
        dotClass: meta.dotClass,
        badgeClass: meta.badgeClass,
        filterQuery: { kondisi: item.kondisi },
      };
    });
  }

  if (activeDimension.value === "perkerasan") {
    const raw =
      activeSource.value === "infrastruktur"
        ? (props.infraSummary?.perkerasan ?? [])
        : (props.porosSummary?.perkerasan ?? []);

    return raw.map((item, idx) => {
      const tone = PERKERASAN_TONES[idx % PERKERASAN_TONES.length];
      const count = Number(item.jumlah ?? 0);
      const meter = Number(item.panjang_meter ?? 0);
      const km =
        item.panjang_km !== undefined
          ? Number(item.panjang_km)
          : Math.round((meter / 1000) * 100) / 100;

      return {
        key: item.perkerasan,
        label: item.perkerasan,
        count,
        panjangKm: km,
        percentage: Math.round((count / denom) * 1000) / 10,
        barClass: tone.barClass,
        dotClass: tone.dotClass,
        badgeClass: tone.badgeClass,
        filterQuery:
          activeSource.value === "infrastruktur"
            ? { jenis_perkerasan: item.perkerasan }
            : { perkerasan: item.perkerasan },
      };
    });
  }

  const rawVerif = props.infraSummary?.verifikasi ?? [];
  return rawVerif.map((item) => {
    const meta = getVerifikasiMeta(item.status_verifikasi);
    const count = Number(item.jumlah ?? 0);
    const km = Math.round((Number(item.panjang_meter ?? 0) / 1000) * 100) / 100;

    return {
      key: item.status_verifikasi,
      label: meta.label,
      count,
      panjangKm: km,
      percentage: Math.round((count / denom) * 1000) / 10,
      barClass: meta.barClass,
      dotClass: meta.dotClass,
      badgeClass: meta.badgeClass,
      filterQuery: { status_verifikasi: item.status_verifikasi },
    };
  });
});

function buildTargetPath(rowFilter: Record<string, string>) {
  const basePath =
    activeSource.value === "infrastruktur"
      ? "/admin/infrastruktur-segmen"
      : "/admin/dataset/jalan-poros-desa";

  const query: Record<string, string> = { ...rowFilter };
  if (props.selectedKecamatanId) {
    query.id_kecamatan = String(props.selectedKecamatanId);
  }
  if (props.selectedDesaId) {
    query.id_desa = String(props.selectedDesaId);
  }

  return { path: basePath, query };
}
</script>

<template>
  <section
    aria-labelledby="spatial-analytics-title"
    class="rounded-xl bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-slate-800 shadow-xs p-4 sm:p-5 flex flex-col justify-between gap-4"
  >
    <!-- Header & Controls -->
    <div class="flex flex-col gap-3">
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2.5">
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <UIcon name="i-lucide-bar-chart-3" class="size-4.5 text-blue-600 dark:text-blue-400 shrink-0" />
            <h2
              id="spatial-analytics-title"
              class="text-base font-bold text-slate-900 dark:text-slate-50 tracking-tight"
            >
              Analisis Jaringan Jalan & Verifikasi
            </h2>
          </div>
          <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
            {{ questionSubtitle }}
          </p>
        </div>

        <!-- Source Toggle -->
        <div
          v-if="canViewInfra && canViewPoros"
          role="group"
          aria-label="Sumber data analisis"
          class="inline-flex items-center p-0.5 rounded-lg bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 self-start shrink-0"
        >
          <button
            type="button"
            class="px-2.5 py-1.5 min-h-9 sm:min-h-8 rounded-md text-xs font-semibold transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            :class="
              activeSource === 'infrastruktur'
                ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-slate-50'
                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
            "
            @click="activeSource = 'infrastruktur'"
          >
            Segmen Fisik
          </button>
          <button
            type="button"
            class="px-2.5 py-1.5 min-h-9 sm:min-h-8 rounded-md text-xs font-semibold transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            :class="
              activeSource === 'poros'
                ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-slate-50'
                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
            "
            @click="activeSource = 'poros'"
          >
            Poros Desa
          </button>
        </div>
      </div>

      <!-- Dimension Tabs -->
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-2.5">
        <div role="tablist" aria-label="Kategori analisis" class="flex flex-wrap items-center gap-1.5">
          <button
            role="tab"
            type="button"
            :aria-selected="activeDimension === 'kondisi'"
            class="px-3 py-1.5 min-h-9 sm:min-h-8 rounded-lg text-xs font-semibold border transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            :class="
              activeDimension === 'kondisi'
                ? 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800'
                : 'bg-transparent text-slate-600 border-transparent hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800/60'
            "
            @click="activeDimension = 'kondisi'"
          >
            Kondisi Jalan
          </button>
          <button
            role="tab"
            type="button"
            :aria-selected="activeDimension === 'perkerasan'"
            class="px-3 py-1.5 min-h-9 sm:min-h-8 rounded-lg text-xs font-semibold border transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            :class="
              activeDimension === 'perkerasan'
                ? 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800'
                : 'bg-transparent text-slate-600 border-transparent hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800/60'
            "
            @click="activeDimension = 'perkerasan'"
          >
            Jenis Perkerasan
          </button>
          <button
            v-if="activeSource === 'infrastruktur'"
            role="tab"
            type="button"
            :aria-selected="activeDimension === 'verifikasi'"
            class="px-3 py-1.5 min-h-9 sm:min-h-8 rounded-lg text-xs font-semibold border transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
            :class="
              activeDimension === 'verifikasi'
                ? 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800'
                : 'bg-transparent text-slate-600 border-transparent hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800/60'
            "
            @click="activeDimension = 'verifikasi'"
          >
            Status Verifikasi
          </button>
        </div>

        <button
          v-if="selectedKondisi && activeDimension === 'kondisi'"
          type="button"
          class="text-xs font-semibold text-blue-700 dark:text-blue-400 hover:underline cursor-pointer min-h-8 px-1"
          @click="emit('select-kondisi', null)"
        >
          Hapus Sorotan Peta ({{ selectedKondisi }})
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="space-y-3 py-2">
      <div class="h-3.5 w-full rounded-full bg-slate-100 dark:bg-slate-800 animate-pulse" />
      <div v-for="i in 4" :key="i" class="h-12 w-full rounded-lg bg-slate-100 dark:bg-slate-800/70 animate-pulse" />
    </div>

    <!-- Error -->
    <div
      v-else-if="activeError"
      class="py-8 text-center flex flex-col items-center justify-center gap-2.5"
    >
      <UIcon name="i-lucide-alert-triangle" class="size-7 text-red-600 dark:text-red-400" />
      <p class="text-sm font-medium text-slate-700 dark:text-slate-300">
        {{ activeError }}
      </p>
      <button
        type="button"
        class="px-3.5 py-2 min-h-10 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-900 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-100 cursor-pointer"
        @click="emit('retry')"
      >
        Muat Ulang Ringkasan
      </button>
    </div>

    <!-- Empty -->
    <div
      v-else-if="rows.length === 0 || totalCount === 0"
      class="py-8 text-center flex flex-col items-center justify-center gap-2"
    >
      <UIcon name="i-lucide-inbox" class="size-7 text-slate-400 dark:text-slate-500" />
      <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
        Belum ada data pada cakupan wilayah ini
      </p>
      <p class="text-xs text-slate-600 dark:text-slate-400 max-w-md">
        Data akan tampil otomatis setelah operator menginput ruas atau segmen infrastruktur ke basis data spasial.
      </p>
      <NuxtLink
        :to="activeSource === 'infrastruktur' ? '/admin/infrastruktur-segmen' : '/admin/dataset/jalan-poros-desa'"
        class="mt-1 inline-flex items-center gap-1.5 px-3.5 py-2 min-h-10 rounded-lg text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white"
      >
        <span>Kelola Data Spasial</span>
        <UIcon name="i-lucide-arrow-right" class="size-3.5" />
      </NuxtLink>
    </div>

    <!-- Real Breakdown Content -->
    <div v-else class="space-y-4">
      <!-- Total Strip + Stacked Bar -->
      <div class="space-y-2">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
          <div class="flex items-baseline gap-2 tabular-nums">
            <span class="text-2xl font-bold text-slate-900 dark:text-slate-50 tracking-tight">
              {{ formatIntId(totalCount) }}
            </span>
            <span class="text-xs font-medium text-slate-600 dark:text-slate-400">
              {{ activeSource === "infrastruktur" ? "segmen tercatat" : "ruas master" }}
            </span>
          </div>
          <span class="text-sm font-bold text-slate-800 dark:text-slate-200 tabular-nums">
            Total {{ formatDecimalId(totalKm, 2) }} km
          </span>
        </div>

        <div
          class="h-3 w-full rounded-full overflow-hidden flex bg-slate-100 dark:bg-slate-800 gap-0.5"
          role="img"
          aria-label="Diagram proporsi distribusi jaringan jalan"
        >
          <div
            v-for="row in rows"
            :key="row.key"
            :class="[row.barClass, 'h-full transition-all duration-300 first:rounded-s-full last:rounded-e-full']"
            :style="{ width: `${Math.max(row.percentage, row.count > 0 ? 2 : 0)}%` }"
            :title="`${row.label}: ${formatIntId(row.count)} (${row.percentage}%)`"
          />
        </div>
      </div>

      <!-- Interactive Rows -->
      <div class="divide-y divide-slate-100 dark:divide-slate-800/80">
        <div
          v-for="row in rows"
          :key="row.key"
          class="py-2.5 flex items-center justify-between gap-3"
        >
          <div class="flex items-center gap-2.5 min-w-0">
            <button
              v-if="activeDimension === 'kondisi'"
              type="button"
              :aria-pressed="selectedKondisi === row.key"
              :title="`Sorot kondisi ${row.label} pada peta di atas`"
              class="inline-flex items-center gap-2 px-2.5 py-1.5 min-h-9 sm:min-h-8 rounded-lg border text-left transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              :class="
                selectedKondisi === row.key
                  ? 'bg-blue-50 border-blue-300 text-blue-800 dark:bg-blue-950/60 dark:border-blue-700 dark:text-blue-200'
                  : 'bg-slate-50/70 border-slate-200/80 text-slate-800 hover:bg-slate-100 dark:bg-slate-800/40 dark:border-slate-700/70 dark:text-slate-200 dark:hover:bg-slate-800'
              "
              @click="emit('select-kondisi', selectedKondisi === row.key ? null : row.key)"
            >
              <span :class="['size-2.5 rounded-full shrink-0', row.dotClass]" />
              <span class="text-xs font-semibold truncate">{{ row.label }}</span>
            </button>

            <div v-else class="flex items-center gap-2 min-w-0">
              <span :class="['size-2.5 rounded-full shrink-0', row.dotClass]" />
              <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate">
                {{ row.label }}
              </span>
            </div>
          </div>

          <div class="flex items-center gap-3 sm:gap-4 shrink-0 tabular-nums">
            <div class="text-right">
              <div class="text-xs font-bold text-slate-900 dark:text-slate-100">
                {{ formatIntId(row.count) }}
                <span class="text-slate-500 dark:text-slate-400 font-medium ms-1">
                  ({{ formatDecimalId(row.percentage, 1) }}%)
                </span>
              </div>
              <div class="text-[11px] text-slate-600 dark:text-slate-400">
                {{ formatDecimalId(row.panjangKm, 2) }} km
              </div>
            </div>

            <NuxtLink
              :to="buildTargetPath(row.filterQuery)"
              :aria-label="`Buka data ${row.label} di tabel modul`"
              class="min-w-9 min-h-9 sm:min-w-8 sm:min-h-8 rounded-lg border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-600 hover:text-blue-700 hover:border-blue-300 dark:text-slate-400 dark:hover:text-blue-300 dark:hover:border-blue-700 transition-colors focus-visible:outline-2 focus-visible:outline-blue-600"
            >
              <UIcon name="i-lucide-arrow-up-right" class="size-3.5" />
            </NuxtLink>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import SpatialAnalyticsCard from "~/components/admin/dashboard/SpatialAnalyticsCard.vue";
import SpatialCommandMap from "~/components/admin/dashboard/SpatialCommandMap.vue";
import SimpulJaringanCard from "~/components/admin/dashboard/SimpulJaringanCard.vue";
import OperationalFeedCard from "~/components/admin/dashboard/OperationalFeedCard.vue";
import { KECAMATAN_ENTRIES } from "~/constants/kecamatan";
import type { NavItem } from "~/stores/auth";
import {
  BOJONEGORO_DEFAULT_BBOX,
  formatDecimalId,
  formatIntId,
  formatRupiahCompact,
  getRoleDisplayLabel,
  type BkkdSummaryData,
  type InfrastructureSummaryResponse,
  type JalanPorosSummaryResponse,
  type KecamatanSeriesItem,
  type MartinStatusData,
} from "~/types/admin-dashboard";

definePageMeta({
  middleware: ["auth", "permission"],
  permission: "dashboard-view",
});

useHead({
  title: "Dashboard Spasial & Infrastruktur - Admin Melarosa",
});

const auth = useAuthStore();
const wilayahStore = useWilayahStore();
const { isAdmin, can, canAny } = usePermission();
const { navTree } = useAdminNavigation();

const {
  connectionStatus: geoportalStatus,
  checking: geoportalChecking,
  error: geoportalError,
  refreshStatus: refreshGeoportalStatus,
} = useGeoportalStatus();

const canViewKecamatan = computed(() =>
  canAny(["batas-kecamatan-view", "batas-kecamatan-manage"]),
);
const canViewDesa = computed(() =>
  canAny(["batas-desa-view", "batas-desa-manage"]),
);
const canViewPoros = computed(() =>
  canAny([
    "jalan-poros-desa-view",
    "jalan-poros-desa.view",
    "jalan-view",
    "jalan-poros-desa-manage",
    "jalan-poros-desa.manage",
  ]),
);
const canViewInfra = computed(() =>
  canAny(["infrastruktur-segmen-view", "infrastruktur-segmen-manage"]),
);
const canViewBkkd = computed(() => can("bkkd-proposal-view"));
const canViewAnggaran = computed(() =>
  canAny(["plotting-anggaran-view", "plotting-anggaran-manage"]),
);
const canViewAudit = computed(() => can("audit-logs-view"));
const canManageLayers = computed(() =>
  canAny(["layers-view", "layers-manage"]),
);

const isUnrestrictedRole = computed(
  () =>
    isAdmin() ||
    auth.hasRole("admin") ||
    auth.hasRole("verifierBappeda") ||
    auth.hasRole("verifikator-bappeda") ||
    (!auth.user?.id_kecamatan && !auth.user?.id_desa),
);

const isTerritoryLocked = computed(
  () =>
    !isUnrestrictedRole.value &&
    Boolean(auth.user?.id_kecamatan || auth.user?.id_desa),
);

const selectedKecamatanId = ref<number | null>(null);
const selectedDesaId = ref<number | null>(null);
const selectedKondisi = ref<string | null>(null);

watch(
  () => [auth.user?.id_kecamatan, auth.user?.id_desa, isUnrestrictedRole.value],
  () => {
    if (isTerritoryLocked.value) {
      selectedKecamatanId.value = auth.user?.id_kecamatan
        ? Number(auth.user.id_kecamatan)
        : null;
      selectedDesaId.value = auth.user?.id_desa
        ? Number(auth.user.id_desa)
        : null;
    }
  },
  { immediate: true },
);

const kecamatanSummary = ref<{
  total_kecamatan: number;
  total_luas_hektar: number;
  bbox?: number[];
} | null>(null);
const desaSummary = ref<{
  total_desa: number;
  total_luas_hektar: number;
  bbox?: number[];
} | null>(null);
const kecamatanSeries = ref<KecamatanSeriesItem[]>([]);
const infraSummary = ref<InfrastructureSummaryResponse | null>(null);
const porosSummary = ref<JalanPorosSummaryResponse | null>(null);
const bkkdSummary = ref<BkkdSummaryData | null>(null);
const martinStatus = ref<MartinStatusData | null>(null);

const loadingWilayah = ref(false);
const loadingKecamatanList = ref(false);
const loadingInfra = ref(false);
const loadingPoros = ref(false);
const loadingBkkd = ref(false);
const loadingMartin = ref(false);

const wilayahError = ref<string | null>(null);
const kecamatanError = ref<string | null>(null);
const infraError = ref<string | null>(null);
const porosError = ref<string | null>(null);
const bkkdError = ref<string | null>(null);
const martinError = ref<string | null>(null);

const isRefreshing = ref(false);
const refreshTrigger = ref(0);
const lastUpdatedLabel = ref("");

function isValidBojonegoroBbox(bbox?: number[] | null): bbox is [number, number, number, number] {
  if (!Array.isArray(bbox) || bbox.length !== 4) return false;
  const [minX, minY, maxX, maxY] = bbox.map(Number);
  return (
    Number.isFinite(minX) &&
    Number.isFinite(minY) &&
    Number.isFinite(maxX) &&
    Number.isFinite(maxY) &&
    minX > 100 &&
    maxX > minX &&
    minY < 0 &&
    maxY > minY
  );
}

const activeKecamatanItem = computed(() => {
  if (!selectedKecamatanId.value) return null;
  return (
    kecamatanSeries.value.find(
      (k) => Number(k.id) === Number(selectedKecamatanId.value),
    ) ?? null
  );
});

const activeDesaName = computed(() => {
  if (!selectedDesaId.value) return null;
  if (
    auth.user?.desa?.id &&
    Number(auth.user.desa.id) === Number(selectedDesaId.value)
  ) {
    return auth.user.desa.nama_desa;
  }
  if (selectedKecamatanId.value) {
    const list =
      wilayahStore.desaByKecamatan[Number(selectedKecamatanId.value)] ?? [];
    const hit = list.find((d) => Number(d.id) === Number(selectedDesaId.value));
    if (hit) return hit.nama_desa;
  }
  return null;
});

const lockedScopeLabel = computed(() => {
  if (auth.user?.desa?.nama_desa) {
    return auth.user?.kecamatan?.nama_kecamatan
      ? `Desa ${auth.user.desa.nama_desa}, Kec. ${auth.user.kecamatan.nama_kecamatan}`
      : `Desa ${auth.user.desa.nama_desa}`;
  }
  if (auth.user?.kecamatan?.nama_kecamatan) {
    return `Kecamatan ${auth.user.kecamatan.nama_kecamatan}`;
  }
  return "Wilayah Kerja Terbatas";
});

const activeTerritoryTitle = computed(() => {
  if (isTerritoryLocked.value) return lockedScopeLabel.value;
  if (activeDesaName.value && activeKecamatanItem.value) {
    return `Desa ${activeDesaName.value}, Kec. ${activeKecamatanItem.value.nama_kecamatan}`;
  }
  if (activeKecamatanItem.value) {
    return `Kecamatan ${activeKecamatanItem.value.nama_kecamatan}`;
  }
  return "Kabupaten Bojonegoro";
});

const activeBbox = computed<number[]>(() => {
  if (selectedDesaId.value && isValidBojonegoroBbox(infraSummary.value?.bbox)) {
    return infraSummary.value!.bbox!;
  }
  if (
    selectedDesaId.value &&
    isValidBojonegoroBbox(porosSummary.value?.bbox)
  ) {
    return porosSummary.value!.bbox!;
  }
  if (
    activeKecamatanItem.value &&
    isValidBojonegoroBbox(activeKecamatanItem.value.bbox)
  ) {
    return activeKecamatanItem.value.bbox!;
  }
  if (selectedKecamatanId.value) {
    const kecName = (
      activeKecamatanItem.value?.nama_kecamatan ||
      auth.user?.kecamatan?.nama_kecamatan ||
      ""
    ).toLowerCase();
    const fallbackCoord = KECAMATAN_ENTRIES.find(
      (k) => k.name.toLowerCase() === kecName,
    );
    if (fallbackCoord && isValidBojonegoroBbox(fallbackCoord.bbox)) {
      return fallbackCoord.bbox;
    }
  }
  if (isValidBojonegoroBbox(kecamatanSummary.value?.bbox)) {
    return kecamatanSummary.value!.bbox!;
  }
  return BOJONEGORO_DEFAULT_BBOX;
});

const greetingText = computed(() => {
  const hour = new Date().getHours();
  if (hour >= 4 && hour < 11) return "Selamat Pagi";
  if (hour >= 11 && hour < 15) return "Selamat Siang";
  if (hour >= 15 && hour < 18) return "Selamat Sore";
  return "Selamat Malam";
});

const roleDisplayLabel = computed(() => getRoleDisplayLabel(auth.user?.roles));

const infraMantapStats = computed(() => {
  const total = Number(infraSummary.value?.total_segmen ?? 0);
  if (total <= 0) return null;
  const list = infraSummary.value?.kondisi ?? [];
  const mantapCount = list
    .filter((k) => {
      const name = (k.kondisi || "").toLowerCase();
      return name.includes("baik") || (name.includes("sedang") && !name.includes("rusak"));
    })
    .reduce((acc, k) => acc + Number(k.jumlah ?? 0), 0);
  return {
    count: mantapCount,
    pct: Math.round((mantapCount / total) * 1000) / 10,
  };
});

const verifikasiStats = computed(() => {
  const list = infraSummary.value?.verifikasi ?? [];
  const mapCount = (key: string) =>
    Number(list.find((v) => v.status_verifikasi === key)?.jumlah ?? 0);

  const verifiedFinal = mapCount("verified_bappeda");
  const pendingQueue =
    mapCount("submitted_desa") + mapCount("verified_kecamatan");
  const draft = mapCount("draft");
  const rejected =
    mapCount("rejected_kecamatan") + mapCount("rejected_bappeda");

  return { verifiedFinal, pendingQueue, draft, rejected };
});

interface QuickModuleItem {
  label: string;
  to: string;
  icon: string;
  groupLabel: string;
}

const quickModules = computed<QuickModuleItem[]>(() => {
  const out: QuickModuleItem[] = [];
  const walk = (items: NavItem[], groupLabel: string) => {
    for (const item of items) {
      if (item.children && item.children.length > 0) {
        walk(item.children, item.label);
      } else if (item.to && item.to !== "/admin") {
        out.push({
          label: item.label,
          to: item.to,
          icon: item.icon || "i-lucide-layers",
          groupLabel,
        });
      }
    }
  };
  walk(navTree.value, "Menu Utama");
  return out.slice(0, 6);
});

async function loadWilayahStats() {
  loadingWilayah.value = true;
  loadingKecamatanList.value = true;
  wilayahError.value = null;
  kecamatanError.value = null;

  const tasks: Promise<void>[] = [];

  if (canViewKecamatan.value) {
    tasks.push(
      $http<any>("admin/batas-wilayah-kecamatan", {
        query: { format: "summary" },
      })
        .then((res) => {
          kecamatanSummary.value = res;
        })
        .catch((err) => {
          wilayahError.value =
            err?.data?.message || "Gagal memuat ringkasan kecamatan.";
        }),
    );

    tasks.push(
      $http<{ ok: boolean; data: KecamatanSeriesItem[] }>(
        "admin/batas-wilayah-kecamatan",
        { query: { format: "series" } },
      )
        .then((res) => {
          kecamatanSeries.value = Array.isArray(res?.data) ? res.data : [];
        })
        .catch((err) => {
          kecamatanError.value =
            err?.data?.message || "Gagal memuat daftar wilayah kecamatan.";
        }),
    );
  }

  if (canViewDesa.value) {
    tasks.push(
      $http<any>("admin/batas-wilayah-desa", { query: { format: "summary" } })
        .then((res) => {
          desaSummary.value = res;
        })
        .catch((err) => {
          if (!wilayahError.value) {
            wilayahError.value =
              err?.data?.message || "Gagal memuat ringkasan desa.";
          }
        }),
    );
  }

  await Promise.allSettled(tasks);
  loadingWilayah.value = false;
  loadingKecamatanList.value = false;
}

async function loadScopedMetrics() {
  const scopeQuery: Record<string, string | number> = { format: "summary" };
  if (selectedKecamatanId.value) {
    scopeQuery.id_kecamatan = selectedKecamatanId.value;
  }
  if (selectedDesaId.value) {
    scopeQuery.id_desa = selectedDesaId.value;
  }

  const tasks: Promise<void>[] = [];

  if (canViewInfra.value) {
    loadingInfra.value = true;
    infraError.value = null;
    tasks.push(
      $http<InfrastructureSummaryResponse>("admin/infrastruktur-segmen", {
        query: scopeQuery,
      })
        .then((res) => {
          infraSummary.value = res;
        })
        .catch((err) => {
          infraError.value =
            err?.data?.message || "Gagal memuat statistik segmen infrastruktur.";
        })
        .finally(() => {
          loadingInfra.value = false;
        }),
    );
  }

  if (canViewPoros.value) {
    loadingPoros.value = true;
    porosError.value = null;
    tasks.push(
      $http<JalanPorosSummaryResponse>("admin/jalan-poros-desa", {
        query: scopeQuery,
      })
        .then((res) => {
          porosSummary.value = res;
        })
        .catch((err) => {
          porosError.value =
            err?.data?.message || "Gagal memuat ringkasan Master Poros Desa.";
        })
        .finally(() => {
          loadingPoros.value = false;
        }),
    );
  }

  if (canViewBkkd.value) {
    loadingBkkd.value = true;
    bkkdError.value = null;
    const bkkdQuery: Record<string, string | number> = { per_page: 1 };
    if (selectedKecamatanId.value) {
      bkkdQuery.id_kecamatan = selectedKecamatanId.value;
    }
    if (selectedDesaId.value) {
      bkkdQuery.id_desa = selectedDesaId.value;
    }

    tasks.push(
      $http<{ summary?: BkkdSummaryData }>("admin/bkkd/proposal", {
        query: bkkdQuery,
      })
        .then((res) => {
          bkkdSummary.value = res?.summary ?? null;
        })
        .catch((err) => {
          bkkdError.value =
            err?.data?.message || "Gagal memuat ringkasan usulan BKKD.";
        })
        .finally(() => {
          loadingBkkd.value = false;
        }),
    );
  }

  await Promise.allSettled(tasks);
}

async function loadMartinStatus(forceRefresh = false) {
  loadingMartin.value = true;
  martinError.value = null;
  try {
    const res = await $http<{ ok: boolean; data: MartinStatusData }>(
      "layers/martin/status",
      {
        query: forceRefresh ? { refresh: 1 } : undefined,
      },
    );
    martinStatus.value = res?.data ?? null;
  } catch (err: any) {
    martinError.value =
      err?.data?.message || "Status server tile Martin belum tersedia.";
  } finally {
    loadingMartin.value = false;
  }
}

async function refreshAllDashboard() {
  isRefreshing.value = true;
  refreshTrigger.value++;
  await Promise.allSettled([
    loadWilayahStats(),
    loadScopedMetrics(),
    loadMartinStatus(true),
    refreshGeoportalStatus(),
  ]);
  lastUpdatedLabel.value = new Date().toLocaleTimeString("id-ID", {
    hour: "2-digit",
    minute: "2-digit",
  });
  isRefreshing.value = false;
}

function handleSelectKecamatan(kecId: number | null) {
  if (isTerritoryLocked.value) return;
  selectedKecamatanId.value = kecId;
  selectedDesaId.value = null;
}

function handleWilayahSelectorChange(val: {
  id_kecamatan: number | null;
  id_desa: number | null;
}) {
  if (isTerritoryLocked.value) return;
  selectedKecamatanId.value = val.id_kecamatan;
  selectedDesaId.value = val.id_desa;
}

function resetTerritoryFilters() {
  if (!isTerritoryLocked.value) {
    selectedKecamatanId.value = null;
    selectedDesaId.value = null;
  }
  selectedKondisi.value = null;
}

watch(
  () => [selectedKecamatanId.value, selectedDesaId.value],
  () => {
    loadScopedMetrics();
  },
);

onMounted(() => {
  lastUpdatedLabel.value = new Date().toLocaleTimeString("id-ID", {
    hour: "2-digit",
    minute: "2-digit",
  });
  loadWilayahStats();
  loadScopedMetrics();
  loadMartinStatus();
});
</script>

<template>
  <div class="space-y-5 pb-6 max-w-[1600px] mx-auto">
    <!-- Command Header & Territory Filter Bar -->
    <section
      aria-labelledby="dashboard-page-title"
      class="rounded-xl bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-slate-800 shadow-xs p-4 sm:p-5"
    >
      <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
        <div class="min-w-0 space-y-1">
          <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800">
              {{ roleDisplayLabel }}
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700">
              <UIcon name="i-lucide-map-pin" class="size-3.5 text-blue-600 dark:text-blue-400 shrink-0" />
              {{ activeTerritoryTitle }}
            </span>
            <span
              v-if="lastUpdatedLabel"
              class="text-xs text-slate-600 dark:text-slate-400 tabular-nums"
            >
              Diperbarui pukul {{ lastUpdatedLabel }} WIB
            </span>
          </div>

          <h1
            id="dashboard-page-title"
            class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-50 tracking-tight"
          >
            {{ greetingText }}, {{ auth.user?.name || "Operator Melarosa" }}
          </h1>
          <p class="text-sm text-slate-600 dark:text-slate-400 max-w-2xl">
            Ringkasan kondisi jaringan jalan desa, segmen infrastruktur terbangun, serta kesehatan layanan geospasial Kabupaten Bojonegoro.
          </p>
        </div>

        <!-- Territory Filter & Primary Actions -->
        <div class="flex flex-col lg:flex-row lg:flex-wrap lg:items-center gap-2.5 shrink-0">
          <div v-if="isUnrestrictedRole && canViewKecamatan" class="w-full sm:w-auto sm:min-w-[340px]">
            <CommonWilayahSelector
              :model-kecamatan="selectedKecamatanId"
              :model-desa="selectedDesaId"
              layout="row"
              allow-all
              all-option-label="Semua"
              size="sm"
              :show-labels="false"
              @change="handleWilayahSelectorChange"
            />
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <button
              v-if="(!isTerritoryLocked && (selectedKecamatanId || selectedDesaId)) || selectedKondisi"
              type="button"
              class="inline-flex items-center gap-1.5 px-3 py-2 min-h-11 sm:min-h-9 rounded-lg text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 dark:text-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:border-slate-700 cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="resetTerritoryFilters"
            >
              <UIcon name="i-lucide-rotate-ccw" class="size-3.5 shrink-0" />
              <span>Reset Filter</span>
            </button>

            <button
              type="button"
              :disabled="isRefreshing"
              class="inline-flex items-center gap-1.5 px-3.5 py-2 min-h-11 sm:min-h-9 rounded-lg text-xs font-semibold text-slate-800 bg-white hover:bg-slate-50 border border-slate-300 dark:text-slate-100 dark:bg-slate-900 dark:hover:bg-slate-800 dark:border-slate-700 cursor-pointer disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-blue-600"
              @click="refreshAllDashboard"
            >
              <UIcon
                name="i-lucide-refresh-cw"
                :class="['size-3.5 shrink-0', isRefreshing && 'animate-spin']"
              />
              <span>Segarkan Data</span>
            </button>

            <NuxtLink
              to="/maps"
              class="inline-flex items-center gap-1.5 px-4 py-2 min-h-11 sm:min-h-9 rounded-lg text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-xs transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
            >
              <UIcon name="i-lucide-compass" class="size-4 shrink-0" />
              <span>Buka Peta WebGIS</span>
            </NuxtLink>
          </div>
        </div>
      </div>
    </section>

    <!-- Primary Verified KPI Strip -->
    <section
      aria-label="Ringkasan indikator spasial"
      class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4"
    >
      <!-- KPI 1: Cakupan Administrasi -->
      <article class="rounded-xl bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-slate-800 shadow-xs p-4 sm:p-5 flex flex-col justify-between gap-3">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
              Cakupan Administrasi
            </h2>
            <div v-if="loadingWilayah" class="h-8 w-36 mt-2 rounded-md bg-slate-100 dark:bg-slate-800 animate-pulse" />
            <p v-else-if="wilayahError" class="text-xs text-red-600 dark:text-red-400 mt-2">
              {{ wilayahError }}
            </p>
            <div v-else-if="activeKecamatanItem" class="mt-1.5 flex items-baseline gap-2 tabular-nums">
              <span class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-slate-50 tracking-tight">
                {{ formatIntId(activeKecamatanItem.jumlah_desa) }} Desa
              </span>
              <span class="text-xs font-medium text-slate-600 dark:text-slate-400">
                (1 Kecamatan)
              </span>
            </div>
            <div v-else-if="canViewKecamatan || canViewDesa" class="mt-1.5 flex items-baseline gap-2 tabular-nums">
              <span class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-slate-50 tracking-tight">
                {{ canViewKecamatan && kecamatanSummary ? formatIntId(kecamatanSummary.total_kecamatan) : "-" }}
                <span class="text-base font-semibold text-slate-600 dark:text-slate-400">Kec.</span>
              </span>
              <span v-if="canViewDesa && desaSummary" class="text-lg font-bold text-slate-700 dark:text-slate-300">
                / {{ formatIntId(desaSummary.total_desa) }} Desa
              </span>
            </div>
            <p v-else class="mt-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
              {{ activeTerritoryTitle }}
            </p>
          </div>

          <div class="size-10 rounded-lg bg-blue-50 dark:bg-blue-950/50 border border-blue-200/70 dark:border-blue-800/60 flex items-center justify-center shrink-0">
            <UIcon name="i-lucide-landmark" class="size-5 text-blue-600 dark:text-blue-400" />
          </div>
        </div>

        <div class="pt-2.5 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between gap-2 text-xs">
          <span class="text-slate-600 dark:text-slate-400 tabular-nums">
            <template v-if="activeKecamatanItem">
              Luas {{ formatDecimalId(activeKecamatanItem.luas_hektar, 0) }} Ha
            </template>
            <template v-else-if="kecamatanSummary?.total_luas_hektar">
              Luas {{ formatDecimalId(kecamatanSummary.total_luas_hektar, 0) }} Ha
            </template>
            <template v-else-if="desaSummary?.total_luas_hektar">
              Luas {{ formatDecimalId(desaSummary.total_luas_hektar, 0) }} Ha
            </template>
            <template v-else>
              Batas wilayah definitif
            </template>
          </span>

          <NuxtLink
            v-if="canViewKecamatan"
            to="/admin/dataset/wilayah-kecamatan"
            class="font-semibold text-blue-700 dark:text-blue-400 hover:underline shrink-0"
          >
            Kelola Batas
          </NuxtLink>
        </div>
      </article>

      <!-- KPI 2: Master Jalan Poros Desa -->
      <article class="rounded-xl bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-slate-800 shadow-xs p-4 sm:p-5 flex flex-col justify-between gap-3">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
              Master Jalan Poros Desa
            </h2>
            <div v-if="loadingPoros" class="h-8 w-36 mt-2 rounded-md bg-slate-100 dark:bg-slate-800 animate-pulse" />
            <p v-else-if="!canViewPoros" class="text-xs text-slate-600 dark:text-slate-400 mt-2">
              Akses dataset dibatasi oleh peran.
            </p>
            <p v-else-if="porosError" class="text-xs text-red-600 dark:text-red-400 mt-2">
              {{ porosError }}
            </p>
            <div v-else class="mt-1.5 flex items-baseline gap-2 tabular-nums">
              <span class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-slate-50 tracking-tight">
                {{ formatDecimalId(porosSummary?.total_panjang_km ?? 0, 2) }}
                <span class="text-base font-semibold text-slate-600 dark:text-slate-400">km</span>
              </span>
            </div>
          </div>

          <div class="size-10 rounded-lg bg-sky-50 dark:bg-sky-950/50 border border-sky-200/70 dark:border-sky-800/60 flex items-center justify-center shrink-0">
            <UIcon name="i-lucide-route" class="size-5 text-sky-600 dark:text-sky-400" />
          </div>
        </div>

        <div class="pt-2.5 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between gap-2 text-xs">
          <span class="text-slate-600 dark:text-slate-400 tabular-nums">
            <template v-if="canViewPoros && porosSummary">
              {{ formatIntId(porosSummary.total_ruas ?? 0) }} ruas master terdaftar
            </template>
            <template v-else>
              Jaringan penghubung antar desa
            </template>
          </span>

          <NuxtLink
            v-if="canViewPoros"
            to="/admin/dataset/jalan-poros-desa"
            class="font-semibold text-blue-700 dark:text-blue-400 hover:underline shrink-0"
          >
            Buka Dataset
          </NuxtLink>
        </div>
      </article>

      <!-- KPI 3: Segmen Infrastruktur Fisik -->
      <article class="rounded-xl bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-slate-800 shadow-xs p-4 sm:p-5 flex flex-col justify-between gap-3">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
              Segmen Infrastruktur Fisik
            </h2>
            <div v-if="loadingInfra" class="h-8 w-36 mt-2 rounded-md bg-slate-100 dark:bg-slate-800 animate-pulse" />
            <p v-else-if="!canViewInfra" class="text-xs text-slate-600 dark:text-slate-400 mt-2">
              Akses segmen dibatasi oleh peran.
            </p>
            <p v-else-if="infraError" class="text-xs text-red-600 dark:text-red-400 mt-2">
              {{ infraError }}
            </p>
            <div v-else class="mt-1.5 flex items-baseline gap-2 tabular-nums">
              <span class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-slate-50 tracking-tight">
                {{ formatDecimalId(infraSummary?.total_panjang_km ?? 0, 2) }}
                <span class="text-base font-semibold text-slate-600 dark:text-slate-400">km</span>
              </span>
            </div>
          </div>

          <div class="size-10 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200/70 dark:border-emerald-800/60 flex items-center justify-center shrink-0">
            <UIcon name="i-lucide-construction" class="size-5 text-emerald-700 dark:text-emerald-400" />
          </div>
        </div>

        <div class="pt-2.5 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between gap-2 text-xs">
          <span class="text-slate-600 dark:text-slate-400 tabular-nums">
            <template v-if="canViewInfra && infraSummary">
              {{ formatIntId(infraSummary.total_segmen ?? 0) }} segmen
              <template v-if="infraMantapStats">
                · {{ formatDecimalId(infraMantapStats.pct, 1) }}% Baik/Sedang
              </template>
            </template>
            <template v-else>
              Inventaris perkerasan lapangan
            </template>
          </span>

          <NuxtLink
            v-if="canViewInfra"
            to="/admin/infrastruktur-segmen"
            class="font-semibold text-blue-700 dark:text-blue-400 hover:underline shrink-0"
          >
            Kelola Segmen
          </NuxtLink>
        </div>
      </article>

      <!-- KPI 4: BKKD Summary OR Verifikasi Pipeline (Permission Adaptive) -->
      <article class="rounded-xl bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-slate-800 shadow-xs p-4 sm:p-5 flex flex-col justify-between gap-3">
        <template v-if="canViewBkkd">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                Usulan Anggaran BKKD
              </h2>
              <div v-if="loadingBkkd" class="h-8 w-36 mt-2 rounded-md bg-slate-100 dark:bg-slate-800 animate-pulse" />
              <p v-else-if="bkkdError" class="text-xs text-red-600 dark:text-red-400 mt-2">
                {{ bkkdError }}
              </p>
              <div v-else class="mt-1.5 flex items-baseline gap-2 tabular-nums">
                <span class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-slate-50 tracking-tight">
                  {{ formatIntId(bkkdSummary?.total_proposal ?? 0) }}
                </span>
                <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">
                  proposal
                </span>
              </div>
            </div>

            <div class="size-10 rounded-lg bg-amber-50 dark:bg-amber-950/50 border border-amber-200/70 dark:border-amber-800/60 flex items-center justify-center shrink-0">
              <UIcon name="i-lucide-file-spreadsheet" class="size-5 text-amber-700 dark:text-amber-400" />
            </div>
          </div>

          <div class="pt-2.5 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between gap-2 text-xs">
            <span class="text-slate-600 dark:text-slate-400 tabular-nums truncate">
              <template v-if="bkkdSummary">
                {{ formatRupiahCompact(bkkdSummary.total_nilai_usulan) }} · {{ formatIntId(bkkdSummary.total_lokasi ?? 0) }} lokasi
              </template>
              <template v-else>
                Bantuan Keuangan Khusus Desa
              </template>
            </span>

            <NuxtLink
              to="/admin/bkkd/proposal"
              class="font-semibold text-blue-700 dark:text-blue-400 hover:underline shrink-0"
            >
              Tinjau BKKD
            </NuxtLink>
          </div>
        </template>

        <template v-else>
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                Pipeline Verifikasi
              </h2>
              <div v-if="loadingInfra" class="h-8 w-36 mt-2 rounded-md bg-slate-100 dark:bg-slate-800 animate-pulse" />
              <div v-else class="mt-1.5 flex items-baseline gap-2 tabular-nums">
                <span class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-slate-50 tracking-tight">
                  {{ formatIntId(verifikasiStats.verifiedFinal) }}
                </span>
                <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                  disetujui final
                </span>
              </div>
            </div>

            <div class="size-10 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200/70 dark:border-emerald-800/60 flex items-center justify-center shrink-0">
              <UIcon name="i-lucide-shield-check" class="size-5 text-emerald-700 dark:text-emerald-400" />
            </div>
          </div>

          <div class="pt-2.5 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between gap-2 text-xs">
            <span class="text-slate-600 dark:text-slate-400 tabular-nums truncate">
              {{ formatIntId(verifikasiStats.pendingQueue) }} antrean · {{ formatIntId(verifikasiStats.draft) }} draft
            </span>

            <NuxtLink
              v-if="canViewInfra"
              to="/admin/infrastruktur-segmen"
              class="font-semibold text-blue-700 dark:text-blue-400 hover:underline shrink-0"
            >
              Verifikasi
            </NuxtLink>
          </div>
        </template>
      </article>
    </section>

    <!-- Primary Interactive Spatial Command Map & Territory Drilldown -->
    <SpatialCommandMap
      :kecamatan-list="kecamatanSeries"
      :loading-kecamatan="loadingKecamatanList"
      :kecamatan-error="kecamatanError"
      :selected-kecamatan-id="selectedKecamatanId"
      :selected-desa-id="selectedDesaId"
      :selected-kondisi="selectedKondisi"
      :active-bbox="activeBbox"
      :is-territory-locked="isTerritoryLocked"
      :locked-scope-label="lockedScopeLabel"
      @select-kecamatan="handleSelectKecamatan"
      @select-kondisi="selectedKondisi = $event"
      @retry-kecamatan="loadWilayahStats"
    />

    <!-- Analytics + Infrastructure Node Health -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-stretch">
      <div class="lg:col-span-7 flex flex-col">
        <SpatialAnalyticsCard
          class="flex-1"
          :infra-summary="infraSummary"
          :poros-summary="porosSummary"
          :loading-infra="loadingInfra"
          :loading-poros="loadingPoros"
          :infra-error="infraError"
          :poros-error="porosError"
          :selected-kondisi="selectedKondisi"
          :selected-kecamatan-id="selectedKecamatanId"
          :selected-desa-id="selectedDesaId"
          :can-view-infra="canViewInfra"
          :can-view-poros="canViewPoros"
          @select-kondisi="selectedKondisi = $event"
          @retry="loadScopedMetrics"
        />
      </div>

      <div class="lg:col-span-5 flex flex-col">
        <SimpulJaringanCard
          class="flex-1"
          :geoportal-status="geoportalStatus"
          :geoportal-checking="geoportalChecking"
          :geoportal-error="geoportalError"
          :martin-status="martinStatus"
          :martin-checking="loadingMartin"
          :martin-error="martinError"
          :can-manage-layers="canManageLayers"
          @refresh="
            loadMartinStatus(true);
            refreshGeoportalStatus();
          "
        />
      </div>
    </div>

    <!-- Operational Feed + Role-Authorized Quick Workspaces -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
      <div class="lg:col-span-8">
        <OperationalFeedCard
          :selected-kecamatan-id="selectedKecamatanId"
          :selected-desa-id="selectedDesaId"
          :can-view-infra="canViewInfra"
          :can-view-bkkd="canViewBkkd"
          :can-view-anggaran="canViewAnggaran"
          :can-view-audit="canViewAudit"
          :refresh-trigger="refreshTrigger"
        />
      </div>

      <section
        aria-labelledby="quick-modules-title"
        class="lg:col-span-4 rounded-xl bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-slate-800 shadow-xs p-4 sm:p-5 space-y-3.5"
      >
        <div>
          <div class="flex items-center gap-2">
            <UIcon name="i-lucide-compass" class="size-4.5 text-blue-600 dark:text-blue-400 shrink-0" />
            <h2
              id="quick-modules-title"
              class="text-base font-bold text-slate-900 dark:text-slate-50 tracking-tight"
            >
              Modul Kerja Terotorisasi
            </h2>
          </div>
          <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
            Akses cepat halaman kerja sesuai izin akun Anda.
          </p>
        </div>

        <div
          v-if="quickModules.length === 0"
          class="py-6 text-center text-xs text-slate-600 dark:text-slate-400"
        >
          Daftar menu modul belum tersedia.
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-2">
          <NuxtLink
            v-for="mod in quickModules"
            :key="mod.to"
            :to="mod.to"
            class="group min-h-12 px-3 py-2.5 rounded-lg border border-slate-200/80 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/50 hover:bg-blue-50/70 hover:border-blue-200 dark:hover:bg-blue-950/40 dark:hover:border-blue-800/70 flex items-center justify-between gap-3 transition-colors focus-visible:outline-2 focus-visible:outline-blue-600"
          >
            <div class="flex items-center gap-2.5 min-w-0">
              <div class="size-8 rounded-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center shrink-0">
                <UIcon
                  :name="mod.icon"
                  class="size-4 text-slate-700 group-hover:text-blue-700 dark:text-slate-300 dark:group-hover:text-blue-400"
                />
              </div>
              <div class="min-w-0">
                <span class="block text-xs font-bold text-slate-900 dark:text-slate-100 truncate">
                  {{ mod.label }}
                </span>
                <span class="block text-[11px] text-slate-600 dark:text-slate-400 truncate">
                  {{ mod.groupLabel }}
                </span>
              </div>
            </div>

            <UIcon
              name="i-lucide-chevron-right"
              class="size-4 text-slate-400 group-hover:text-blue-600 dark:text-slate-500 dark:group-hover:text-blue-400 shrink-0"
            />
          </NuxtLink>
        </div>
      </section>
    </div>
  </div>
</template>

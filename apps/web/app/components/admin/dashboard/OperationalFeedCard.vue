<script setup lang="ts">
import {
  formatDecimalId,
  formatRelativeTimeId,
  formatRupiahCompact,
  getKondisiMeta,
  getVerifikasiMeta,
} from "~/types/admin-dashboard";

interface FeedTab {
  id: "segmen" | "bkkd" | "anggaran" | "audit";
  label: string;
  to: string;
}

interface Props {
  selectedKecamatanId: number | null;
  selectedDesaId: number | null;
  canViewInfra: boolean;
  canViewBkkd: boolean;
  canViewAnggaran: boolean;
  canViewAudit: boolean;
  refreshTrigger: number;
}

const props = defineProps<Props>();

const availableTabs = computed<FeedTab[]>(() => {
  const tabs: FeedTab[] = [];
  if (props.canViewInfra) {
    tabs.push({
      id: "segmen",
      label: "Segmen Infrastruktur",
      to: "/admin/infrastruktur-segmen",
    });
  }
  if (props.canViewBkkd) {
    tabs.push({
      id: "bkkd",
      label: "Usulan BKKD",
      to: "/admin/bkkd/proposal",
    });
  }
  if (props.canViewAnggaran) {
    tabs.push({
      id: "anggaran",
      label: "Plotting Anggaran",
      to: "/admin/plotting-anggaran",
    });
  }
  if (props.canViewAudit) {
    tabs.push({
      id: "audit",
      label: "Log Aktivitas",
      to: "/admin/audit-logs",
    });
  }
  return tabs;
});

const activeTabId = ref<FeedTab["id"]>("segmen");

watch(
  availableTabs,
  (list) => {
    if (list.length > 0 && !list.some((t) => t.id === activeTabId.value)) {
      activeTabId.value = list[0].id;
    }
  },
  { immediate: true },
);

const activeTab = computed(
  () => availableTabs.value.find((t) => t.id === activeTabId.value) ?? availableTabs.value[0],
);

const loading = ref(false);
const errorMsg = ref<string | null>(null);
const items = ref<any[]>([]);

async function loadFeed() {
  if (!activeTab.value) {
    items.value = [];
    return;
  }

  loading.value = true;
  errorMsg.value = null;

  try {
    const query: Record<string, string | number> = { per_page: 5 };
    if (activeTabId.value !== "audit") {
      if (props.selectedKecamatanId) query.id_kecamatan = props.selectedKecamatanId;
      if (props.selectedDesaId) query.id_desa = props.selectedDesaId;
    }

    let endpoint = "admin/infrastruktur-segmen";
    if (activeTabId.value === "bkkd") endpoint = "admin/bkkd/proposal";
    if (activeTabId.value === "anggaran") endpoint = "admin/plotting-anggaran";
    if (activeTabId.value === "audit") endpoint = "admin/audit-logs";

    const res = await $http<any>(endpoint, { query });

    if (activeTabId.value === "audit") {
      const paginated = res?.data;
      items.value = Array.isArray(paginated?.data) ? paginated.data : Array.isArray(res?.data) ? res.data : [];
    } else if (activeTabId.value === "segmen") {
      const list = Array.isArray(res?.data) ? res.data : [];
      items.value = list.map((row: any) => row?.properties ?? row);
    } else {
      items.value = Array.isArray(res?.data) ? res.data : Array.isArray(res?.data?.data) ? res.data.data : [];
    }
  } catch (err: any) {
    errorMsg.value = err?.data?.message || "Gagal memuat aktivitas operasional terbaru.";
    items.value = [];
  } finally {
    loading.value = false;
  }
}

watch(
  () => [
    activeTabId.value,
    availableTabs.value.map((t) => t.id).join(","),
    props.selectedKecamatanId,
    props.selectedDesaId,
    props.refreshTrigger,
  ],
  () => {
    loadFeed();
  },
  { immediate: true },
);

function resolveAuditEventLabel(ev?: string) {
  switch (ev) {
    case "created":
      return "Dibuat";
    case "updated":
      return "Diperbarui";
    case "deleted":
      return "Dihapus";
    case "verified":
      return "Diverifikasi";
    case "rejected":
      return "Ditolak";
    default:
      return ev || "Aktivitas";
  }
}
</script>

<template>
  <section
    aria-labelledby="operational-feed-title"
    class="rounded-xl bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-slate-800 shadow-xs p-4 sm:p-5 flex flex-col gap-4"
  >
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5">
      <div class="min-w-0">
        <div class="flex items-center gap-2">
          <UIcon name="i-lucide-activity" class="size-4.5 text-blue-600 dark:text-blue-400 shrink-0" />
          <h2
            id="operational-feed-title"
            class="text-base font-bold text-slate-900 dark:text-slate-50 tracking-tight"
          >
            Aktivitas & Antrean Operasional
          </h2>
        </div>
        <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
          Pembaruan data lapangan, usulan desa, anggaran, serta jejak audit terotorisasi.
        </p>
      </div>

      <NuxtLink
        v-if="activeTab"
        :to="activeTab.to"
        class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-700 dark:text-blue-400 hover:underline self-start sm:self-auto min-h-8"
      >
        <span>Lihat Semua</span>
        <UIcon name="i-lucide-arrow-right" class="size-3.5" />
      </NuxtLink>
    </div>

    <!-- Permission-Gated Tabs -->
    <div
      v-if="availableTabs.length > 0"
      role="tablist"
      aria-label="Filter jenis aktivitas"
      class="flex flex-wrap items-center gap-1.5 border-b border-slate-200 dark:border-slate-800 pb-2.5"
    >
      <button
        v-for="tab in availableTabs"
        :key="tab.id"
        role="tab"
        type="button"
        :aria-selected="activeTabId === tab.id"
        class="px-3 py-1.5 min-h-9 sm:min-h-8 rounded-lg text-xs font-semibold border transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-blue-600"
        :class="
          activeTabId === tab.id
            ? 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800'
            : 'bg-transparent text-slate-600 border-transparent hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800/60'
        "
        @click="activeTabId = tab.id"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Empty Permission Fallback -->
    <div
      v-if="availableTabs.length === 0"
      class="py-8 text-center text-xs text-slate-600 dark:text-slate-400"
    >
      Belum ada modul umpan aktivitas yang dibuka untuk peran akun Anda.
    </div>

    <!-- Loading -->
    <div v-else-if="loading" class="space-y-2.5 py-1">
      <div
        v-for="i in 4"
        :key="i"
        class="h-14 rounded-lg bg-slate-100 dark:bg-slate-800/70 animate-pulse"
      />
    </div>

    <!-- Error -->
    <div
      v-else-if="errorMsg"
      class="py-8 text-center flex flex-col items-center gap-2.5"
    >
      <UIcon name="i-lucide-alert-circle" class="size-6 text-red-600 dark:text-red-400" />
      <p class="text-xs font-medium text-slate-700 dark:text-slate-300">
        {{ errorMsg }}
      </p>
      <button
        type="button"
        class="px-3 py-1.5 min-h-9 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-900 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-100 cursor-pointer"
        @click="loadFeed"
      >
        Muat Ulang Daftar
      </button>
    </div>

    <!-- Empty Data -->
    <div
      v-else-if="items.length === 0"
      class="py-8 text-center flex flex-col items-center gap-2"
    >
      <UIcon name="i-lucide-folder-open" class="size-6 text-slate-400 dark:text-slate-500" />
      <p class="text-xs font-medium text-slate-600 dark:text-slate-400">
        Belum ada catatan terbaru pada kategori {{ activeTab?.label }}.
      </p>
    </div>

    <!-- Real Items List -->
    <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800/80">
      <!-- Segmen Infrastruktur Items -->
      <template v-if="activeTabId === 'segmen'">
        <li
          v-for="item in items"
          :key="item.id"
          class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2"
        >
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate">
                {{ item.namobj || "Segmen Infrastruktur" }}
              </span>
              <span
                v-if="item.kondisi"
                :class="['px-2 py-0.5 rounded-md text-[11px] font-medium border', getKondisiMeta(item.kondisi).badgeClass]"
              >
                {{ item.kondisi }}
              </span>
            </div>
            <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5 truncate">
              <template v-if="item.kecamatan">Kec. {{ item.kecamatan }}</template>
              <template v-if="item.desa"> / Desa {{ item.desa }}</template>
              <template v-if="item.jenis_perkerasan"> · {{ item.jenis_perkerasan }}</template>
            </p>
          </div>

          <div class="flex items-center sm:flex-col sm:items-end justify-between gap-1 shrink-0 tabular-nums">
            <span
              :class="[
                'px-2 py-0.5 rounded-md text-[11px] font-semibold border',
                getVerifikasiMeta(item.status_verifikasi).badgeClass,
              ]"
            >
              {{ getVerifikasiMeta(item.status_verifikasi).label }}
            </span>
            <span class="text-[11px] text-slate-600 dark:text-slate-400">
              {{ item.panjang ? `${formatDecimalId(Number(item.panjang) / 1000, 2)} km` : "-" }}
              · {{ formatRelativeTimeId(item.updated_at || item.created_at) }}
            </span>
          </div>
        </li>
      </template>

      <!-- Usulan BKKD Items -->
      <template v-else-if="activeTabId === 'bkkd'">
        <li
          v-for="item in items"
          :key="item.id"
          class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2"
        >
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate">
                {{ item.judul_kegiatan || item.nomor_registrasi || "Usulan BKKD" }}
              </span>
              <span
                v-if="item.tahun_anggaran"
                class="px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 tabular-nums"
              >
                TA {{ item.tahun_anggaran }}
              </span>
            </div>
            <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5 truncate">
              {{ item.nomor_registrasi }}
              <template v-if="item.lokasi_kegiatan"> · {{ item.lokasi_kegiatan }}</template>
            </p>
          </div>

          <div class="flex items-center sm:flex-col sm:items-end justify-between gap-1 shrink-0 tabular-nums">
            <span class="text-xs font-bold text-slate-900 dark:text-slate-100">
              {{ formatRupiahCompact(item.nilai_usulan) }}
            </span>
            <span class="text-[11px] text-slate-600 dark:text-slate-400">
              {{ item.jumlah_lokasi ?? 0 }} lokasi · {{ formatRelativeTimeId(item.updated_at || item.created_at) }}
            </span>
          </div>
        </li>
      </template>

      <!-- Plotting Anggaran Items -->
      <template v-else-if="activeTabId === 'anggaran'">
        <li
          v-for="item in items"
          :key="item.id"
          class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2"
        >
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate">
                {{ item.nama_kegiatan || "Paket Anggaran" }}
              </span>
              <span
                v-if="item.sumber_dana"
                class="px-2 py-0.5 rounded-md text-[11px] font-medium bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800"
              >
                {{ item.sumber_dana }}
              </span>
            </div>
            <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5 truncate">
              {{ item.lokasi_kegiatan || "-" }}
              <template v-if="item.tahun_anggaran"> · TA {{ item.tahun_anggaran }}</template>
            </p>
          </div>

          <div class="flex items-center sm:flex-col sm:items-end justify-between gap-1 shrink-0 tabular-nums">
            <span class="text-xs font-bold text-slate-900 dark:text-slate-100">
              {{ formatRupiahCompact(item.target_pagu_anggaran) }}
            </span>
            <span class="text-[11px] text-slate-600 dark:text-slate-400">
              Target {{ formatDecimalId(Number(item.target_panjang_m ?? 0) / 1000, 2) }} km
            </span>
          </div>
        </li>
      </template>

      <!-- Audit Logs Items -->
      <template v-else>
        <li
          v-for="item in items"
          :key="item.id"
          class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2"
        >
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate">
                {{ item.target_label || item.module || "Aktivitas Sistem" }}
              </span>
              <span class="px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                {{ resolveAuditEventLabel(item.event) }}
              </span>
            </div>
            <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5 truncate">
              {{ item.user?.name || "Sistem" }}
              <template v-if="item.description"> · {{ item.description }}</template>
            </p>
          </div>

          <span class="text-[11px] text-slate-600 dark:text-slate-400 shrink-0 tabular-nums">
            {{ formatRelativeTimeId(item.created_at) }}
          </span>
        </li>
      </template>
    </ul>
  </section>
</template>

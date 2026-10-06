<script lang="ts" setup>
definePageMeta({
  middleware: ["auth", "permission"],
  permission: "audit-logs-view",
});

useSeoMeta({
  title: "Audit Log Aktivitas | Monitoring Geospasial",
});

interface AuditUser {
  id: number;
  name: string;
  email?: string;
}

interface AuditLogItem {
  id: number;
  user_id: number | null;
  event: string;
  module: string;
  auditable_type: string | null;
  auditable_id: number | string | null;
  target_label: string | null;
  description: string;
  before: Record<string, any> | null;
  after: Record<string, any> | null;
  meta: Record<string, any> | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
  user: AuditUser | null;
}

interface AuditLogsResponse {
  ok: boolean;
  data: {
    data: AuditLogItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  options: {
    users: AuditUser[];
    modules: string[];
    events: string[];
  };
}

import AdminAuditLogsFilterBar from "~/components/admin/audit-logs/FilterBar.vue";

const dayjs = useDayjs();
const toast = useToast();

// ─── Filter & State ─────────────────────────────────────────────────────────
const search = ref("");
const selectedModule = ref<string>("ALL");
const selectedEvent = ref<string>("ALL");
const selectedUserId = ref<string>("ALL");
const dateFrom = ref("");
const dateTo = ref("");
const page = ref(1);
const selectedDatePreset = ref<"all" | "today" | "7days" | "30days">("all");

const queryParams = computed(() => ({
  page: page.value,
  search: search.value.trim() || undefined,
  module: selectedModule.value !== "ALL" ? selectedModule.value : undefined,
  event: selectedEvent.value !== "ALL" ? selectedEvent.value : undefined,
  user_id: selectedUserId.value !== "ALL" ? selectedUserId.value : undefined,
  date_from: dateFrom.value || undefined,
  date_to: dateTo.value || undefined,
}));

const { data, status, refresh, error } = useHttp<AuditLogsResponse>("admin/audit-logs", {
  query: queryParams,
  watch: [queryParams],
});

const loading = computed(() => status.value === "pending");

// ─── Dropdown Filter Items ──────────────────────────────────────────────────
const moduleFilterItems = computed(() => {
  const list = (data.value?.options?.modules || []).map((m) => ({
    label: formatModuleName(m),
    value: m,
  }));
  return [{ label: "Semua Modul", value: "ALL" }, ...list];
});

const eventFilterItems = computed(() => {
  const list = (data.value?.options?.events || []).map((e) => ({
    label: formatEventName(e),
    value: e,
  }));
  return [{ label: "Semua Aksi", value: "ALL" }, ...list];
});

const userFilterItems = computed(() => {
  const list = (data.value?.options?.users || []).map((u) => ({
    label: u.name,
    value: String(u.id),
  }));
  return [{ label: "Semua Pelaku", value: "ALL" }, ...list];
});

// ─── Active Filter Chips & Counter ──────────────────────────────────────────
interface ActiveFilterChip {
  id: string;
  label: string;
  clear: () => void;
}

const activeFiltersCount = computed(() => {
  let count = 0;
  if (search.value.trim()) count++;
  if (selectedModule.value !== "ALL") count++;
  if (selectedEvent.value !== "ALL") count++;
  if (selectedUserId.value !== "ALL") count++;
  if (dateFrom.value || dateTo.value) count++;
  return count;
});

const activeFilterChips = computed<ActiveFilterChip[]>(() => {
  const chips: ActiveFilterChip[] = [];

  if (search.value.trim()) {
    chips.push({
      id: "search",
      label: `Cari: "${search.value.trim()}"`,
      clear: () => {
        search.value = "";
      },
    });
  }

  if (selectedModule.value !== "ALL") {
    chips.push({
      id: "module",
      label: `Modul: ${formatModuleName(selectedModule.value)}`,
      clear: () => {
        selectedModule.value = "ALL";
      },
    });
  }

  if (selectedEvent.value !== "ALL") {
    chips.push({
      id: "event",
      label: `Aksi: ${formatEventName(selectedEvent.value)}`,
      clear: () => {
        selectedEvent.value = "ALL";
      },
    });
  }

  if (selectedUserId.value !== "ALL") {
    const userObj = data.value?.options?.users?.find((u) => String(u.id) === selectedUserId.value);
    chips.push({
      id: "user",
      label: `User: ${userObj?.name || selectedUserId.value}`,
      clear: () => {
        selectedUserId.value = "ALL";
      },
    });
  }

  if (dateFrom.value || dateTo.value) {
    const from = dateFrom.value ? dayjs(dateFrom.value).format("DD/MM/YY") : "Awal";
    const to = dateTo.value ? dayjs(dateTo.value).format("DD/MM/YY") : "Kini";
    chips.push({
      id: "date",
      label: `Tgl: ${from} - ${to}`,
      clear: () => {
        dateFrom.value = "";
        dateTo.value = "";
      },
    });
  }

  return chips;
});

// Quick Presets
function setDatePreset(type: "today" | "7days" | "30days" | "all") {
  selectedDatePreset.value = type;
  if (type === "today") {
    dateFrom.value = dayjs().format("YYYY-MM-DD");
    dateTo.value = dayjs().format("YYYY-MM-DD");
  } else if (type === "7days") {
    dateFrom.value = dayjs().subtract(7, "day").format("YYYY-MM-DD");
    dateTo.value = dayjs().format("YYYY-MM-DD");
  } else if (type === "30days") {
    dateFrom.value = dayjs().subtract(30, "day").format("YYYY-MM-DD");
    dateTo.value = dayjs().format("YYYY-MM-DD");
  } else {
    dateFrom.value = "";
    dateTo.value = "";
  }
}

function resetFilters() {
  search.value = "";
  selectedModule.value = "ALL";
  selectedEvent.value = "ALL";
  selectedUserId.value = "ALL";
  dateFrom.value = "";
  dateTo.value = "";
  selectedDatePreset.value = "all";
  page.value = 1;
}

watch([dateFrom, dateTo], () => {
  if (!dateFrom.value && !dateTo.value) {
    selectedDatePreset.value = "all";
  }
});

watch([search, selectedModule, selectedEvent, selectedUserId, dateFrom, dateTo], () => {
  page.value = 1;
});

// ─── Module Metadata & Helpers ──────────────────────────────────────────────
interface ModuleMeta {
  label: string;
  icon: string;
  badgeClass: string;
}

const moduleMetaMap: Record<string, ModuleMeta> = {
  "jalan-poros-desa": {
    label: "Jalan Poros Desa",
    icon: "i-lucide-route",
    badgeClass: "bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border-blue-200 dark:border-blue-800/40",
  },
  "batas-wilayah-desa": {
    label: "Batas Wilayah Desa",
    icon: "i-lucide-map-pin",
    badgeClass: "bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border-blue-200 dark:border-blue-800/40",
  },
  "batas-wilayah-kecamatan": {
    label: "Batas Kecamatan",
    icon: "i-lucide-map",
    badgeClass: "bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800/40",
  },
  users: {
    label: "Pengguna",
    icon: "i-lucide-user",
    badgeClass: "bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border-amber-200 dark:border-amber-800/40",
  },
  roles: {
    label: "Akses Grup (Roles)",
    icon: "i-lucide-shield-check",
    badgeClass: "bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-400 border-purple-200 dark:border-purple-800/40",
  },
  permissions: {
    label: "Hak Akses",
    icon: "i-lucide-key",
    badgeClass: "bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border-rose-200 dark:border-rose-800/40",
  },
  auth: {
    label: "Autentikasi",
    icon: "i-lucide-log-in",
    badgeClass: "bg-cyan-50 text-cyan-700 dark:bg-cyan-950/40 dark:text-cyan-400 border-cyan-200 dark:border-cyan-800/40",
  },
  security: {
    label: "Keamanan Akun",
    icon: "i-lucide-shield",
    badgeClass: "bg-orange-50 text-orange-700 dark:bg-orange-950/40 dark:text-orange-400 border-orange-200 dark:border-orange-800/40",
  },
};

function getModuleMeta(module: string): ModuleMeta {
  return (
    moduleMetaMap[module] || {
      label: module ? module.replace(/-/g, " ").replace(/\b\w/g, (c) => c.toUpperCase()) : "Umum",
      icon: "i-lucide-layers",
      badgeClass: "bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700",
    }
  );
}

function formatModuleName(module: string): string {
  return getModuleMeta(module).label;
}

function formatEventName(event: string): string {
  const ev = (event || "").toLowerCase();

  // Auth events
  if (ev === "login") return "Masuk Sesi (Login)";
  if (ev === "login.failed") return "Login Gagal";
  if (ev === "login.blocked") return "Login Ditolak (Akun Nonaktif)";
  if (ev === "logout") return "Keluar Sesi (Logout)";
  if (ev === "register") return "Registrasi Akun";
  if (ev === "oauth.login") return "Login via OAuth";
  if (ev === "oauth.register") return "Registrasi via OAuth";
  if (ev === "password.reset.request") return "Permintaan Reset Password";
  if (ev === "password.reset") return "Reset Password";
  if (ev === "email.verified") return "Email Diverifikasi";
  if (ev === "email.verification.sent") return "Kirim Ulang Verifikasi Email";

  // Security events
  if (ev === "profile.updated") return "Perbarui Profil";
  if (ev === "password.changed") return "Ganti Password";
  if (ev === "device.disconnected") return "Putus Sesi Perangkat";
  if (ev === "user.status.changed") return "Ubah Status Pengguna";
  if (ev === "user.password.reset.admin") return "Reset Password oleh Admin";
  if (ev === "user.roles.updated") return "Perbarui Role Pengguna";

  // Existing CRUD & Spatial
  if (ev === "created") return "Penambahan (Create)";
  if (ev === "updated") return "Pembaruan (Update)";
  if (ev === "deleted") return "Penghapusan (Delete)";
  if (ev === "split") return "Pemotongan (Split)";

  return event;
}

interface EventBadge {
  label: string;
  color: "success" | "warning" | "error" | "primary" | "info" | "neutral";
  icon: string;
}

function getEventBadge(event: string): EventBadge {
  const ev = (event || "").toLowerCase();
  if (ev === "created" || ev === "insert") {
    return { label: "Created", color: "success", icon: "i-lucide-plus" };
  }
  if (ev === "updated" || ev === "edit") {
    return { label: "Updated", color: "warning", icon: "i-lucide-edit-3" };
  }
  if (ev === "deleted" || ev === "destroy") {
    return { label: "Deleted", color: "error", icon: "i-lucide-trash-2" };
  }
  if (ev === "split") {
    return { label: "Split", color: "primary", icon: "i-lucide-split" };
  }
  if (ev.includes("failed") || ev.includes("blocked")) {
    return { label: "Peringatan", color: "error", icon: "i-lucide-alert-triangle" };
  }
  if (ev === "login" || ev === "logout" || ev.startsWith("oauth.") || ev === "register") {
    return { label: "Sesi", color: "info", icon: "i-lucide-log-in" };
  }
  if (ev.includes("password") || ev.includes("email") || ev.includes("device") || ev.includes("profile") || ev.includes("status")) {
    return { label: "Keamanan", color: "warning", icon: "i-lucide-shield-alert" };
  }
  if (ev.includes("role")) {
    return { label: "Akses", color: "primary", icon: "i-lucide-key" };
  }
  return { label: event || "Event", color: "neutral", icon: "i-lucide-circle" };
}

// ─── Modal Detail & Diff Viewer ─────────────────────────────────────────────
const isDetailOpen = ref(false);
const activeLog = ref<AuditLogItem | null>(null);
const detailTab = ref<string>("diff");
const diffFilterMode = ref<"all" | "changed">("changed");
const expandedSpatialKeys = ref<Record<string, boolean>>({});

function toggleSpatialExpand(key: string) {
  expandedSpatialKeys.value[key] = !expandedSpatialKeys.value[key];
}

function openDetail(log: AuditLogItem) {
  activeLog.value = log;
  detailTab.value = "diff";
  expandedSpatialKeys.value = {};

  const beforeObj = log.before || {};
  const afterObj = log.after || {};
  const hasChanges = Object.keys({ ...beforeObj, ...afterObj }).some(
    (k) => JSON.stringify(beforeObj[k]) !== JSON.stringify(afterObj[k])
  );
  diffFilterMode.value = hasChanges ? "changed" : "all";
  isDetailOpen.value = true;
}

type DiffChangeType = "added" | "removed" | "modified" | "unchanged";

interface DiffRow {
  key: string;
  before: any;
  after: any;
  isChanged: boolean;
  changeType: DiffChangeType;
  isSpatial: boolean;
  spatialSummary?: string | null;
}

function getSpatialSummary(val: any): string | null {
  if (!val) return null;
  if (typeof val === "object") {
    if (val.type && val.coordinates) {
      if (val.type === "Polygon") {
        const ringCount = Array.isArray(val.coordinates) ? val.coordinates.length : 0;
        const ptCount = Array.isArray(val.coordinates?.[0]) ? val.coordinates[0].length : 0;
        return `Polygon (${ringCount} ring, ~${ptCount} koordinat)`;
      }
      if (val.type === "MultiPolygon") {
        const polyCount = Array.isArray(val.coordinates) ? val.coordinates.length : 0;
        return `MultiPolygon (${polyCount} bagian)`;
      }
      if (val.type === "LineString") {
        const ptCount = Array.isArray(val.coordinates) ? val.coordinates.length : 0;
        return `LineString (${ptCount} titik jalur)`;
      }
      if (val.type === "Point") {
        const coords = Array.isArray(val.coordinates) ? val.coordinates.join(", ") : "";
        return `Point [${coords}]`;
      }
      return `GeoJSON ${val.type}`;
    }
  }
  if (typeof val === "string") {
    const trimmed = val.trim();
    if (trimmed.startsWith("POLYGON")) return "WKT Polygon";
    if (trimmed.startsWith("MULTIPOLYGON")) return "WKT MultiPolygon";
    if (trimmed.startsWith("LINESTRING")) return "WKT LineString";
    if (trimmed.startsWith("POINT")) return "WKT Point";
  }
  return null;
}

const diffRows = computed<DiffRow[]>(() => {
  if (!activeLog.value) return [];
  const beforeObj = activeLog.value.before || {};
  const afterObj = activeLog.value.after || {};

  const allKeys = Array.from(new Set([...Object.keys(beforeObj), ...Object.keys(afterObj)]));

  return allKeys.map((key) => {
    const hasBefore = Object.prototype.hasOwnProperty.call(beforeObj, key) && beforeObj[key] !== undefined;
    const hasAfter = Object.prototype.hasOwnProperty.call(afterObj, key) && afterObj[key] !== undefined;
    const beforeVal = beforeObj[key];
    const afterVal = afterObj[key];
    const isChanged = JSON.stringify(beforeVal) !== JSON.stringify(afterVal);

    let changeType: DiffChangeType = "unchanged";
    if ((!hasBefore || beforeVal === null) && hasAfter && afterVal !== null) {
      changeType = "added";
    } else if (hasBefore && beforeVal !== null && (!hasAfter || afterVal === null)) {
      changeType = "removed";
    } else if (isChanged) {
      changeType = "modified";
    }

    const lowerKey = key.toLowerCase();
    const isSpatial =
      ["geom", "geometry", "coordinates", "geojson", "wkt"].includes(lowerKey) ||
      Boolean(beforeVal && typeof beforeVal === "object" && beforeVal.type && beforeVal.coordinates) ||
      Boolean(afterVal && typeof afterVal === "object" && afterVal.type && afterVal.coordinates);

    const spatialSummary = isSpatial ? (getSpatialSummary(afterVal) || getSpatialSummary(beforeVal)) : null;

    return {
      key,
      before: beforeVal,
      after: afterVal,
      isChanged,
      changeType,
      isSpatial,
      spatialSummary,
    };
  });
});

const isDeleteEvent = computed(() => {
  if (!activeLog.value) return false;
  const ev = (activeLog.value.event || "").toLowerCase();
  if (ev === "deleted" || ev === "destroy") return true;
  const hasBefore = Boolean(activeLog.value.before && Object.keys(activeLog.value.before).length > 0);
  const hasAfter = Boolean(activeLog.value.after && Object.keys(activeLog.value.after).length > 0);
  return hasBefore && !hasAfter;
});

const isCreateEvent = computed(() => {
  if (!activeLog.value) return false;
  const ev = (activeLog.value.event || "").toLowerCase();
  if (ev === "created" || ev === "insert") return true;
  const hasBefore = Boolean(activeLog.value.before && Object.keys(activeLog.value.before).length > 0);
  const hasAfter = Boolean(activeLog.value.after && Object.keys(activeLog.value.after).length > 0);
  return !hasBefore && hasAfter;
});

const isUpdateEvent = computed(() => !isDeleteEvent.value && !isCreateEvent.value);

const totalDiffCount = computed(() => diffRows.value.length);
const changedDiffCount = computed(() => diffRows.value.filter((r) => r.isChanged).length);

const filteredDiffRows = computed(() => {
  let list = diffRows.value;
  // Only filter by changed status if it's an update event
  if (isUpdateEvent.value && diffFilterMode.value === "changed") {
    list = list.filter((r) => r.isChanged);
  }
  return list;
});

const diffTableColumns = computed(() => {
  if (isDeleteEvent.value) {
    return [
      { accessorKey: "key", header: "Nama Atribut", class: "w-1/3 min-w-[150px]" },
      { accessorKey: "before", header: "Nilai Sebelum Dihapus" },
    ];
  }
  if (isCreateEvent.value) {
    return [
      { accessorKey: "key", header: "Nama Atribut", class: "w-1/3 min-w-[150px]" },
      { accessorKey: "after", header: "Nilai Tersimpan" },
    ];
  }
  return [
    { accessorKey: "key", header: "Atribut", class: "w-1/4 min-w-[140px]" },
    { accessorKey: "before", header: "Sebelum", class: "w-[35%]" },
    { accessorKey: "after", header: "Sesudah", class: "w-[35%]" },
    { id: "status", header: "Status", class: "w-20 text-center" },
  ];
});

const detailTabs = computed(() => [
  {
    label: isDeleteEvent.value
      ? "Snapshot Data Terhapus"
      : isCreateEvent.value
        ? "Data Record Baru"
        : "Tabel Perubahan (Diff)",
    icon: isDeleteEvent.value
      ? "i-lucide-trash-2"
      : isCreateEvent.value
        ? "i-lucide-plus-circle"
        : "i-lucide-git-compare",
    value: "diff",
    slot: "diff",
  },
  {
    label: "Raw JSON",
    icon: "i-lucide-code-2",
    value: "raw",
    slot: "raw",
  },
]);

function formatDiffValue(val: any): string {
  if (val === null || val === undefined) return "-";
  if (typeof val === "boolean") return val ? "true" : "false";
  if (typeof val === "object") return JSON.stringify(val, null, 2);
  return String(val);
}

function copyJsonPayload() {
  if (!activeLog.value) return;
  const payload = {
    id: activeLog.value.id,
    event: activeLog.value.event,
    module: activeLog.value.module,
    target: activeLog.value.target_label,
    actor: activeLog.value.user,
    before: activeLog.value.before,
    after: activeLog.value.after,
    meta: activeLog.value.meta,
    created_at: activeLog.value.created_at,
  };
  navigator.clipboard.writeText(JSON.stringify(payload, null, 2));
  toast.add({
    title: "Payload berhasil disalin",
    description: "Data JSON audit log telah disimpan ke clipboard.",
    icon: "i-lucide-clipboard-check",
    color: "success",
  });
}

// ─── Table Columns ──────────────────────────────────────────────────────────
const columns = [
  { accessorKey: "created_at", header: "Waktu & Tanggal", class: "w-44 min-w-44 max-w-44" },
  { accessorKey: "user", header: "Pelaku (User)", class: "w-48" },
  { accessorKey: "module", header: "Modul", class: "w-48" },
  { accessorKey: "event", header: "Aksi", class: "w-28 text-center" },
  { accessorKey: "target_label", header: "Target Data", class: "min-w-[160px]" },
  { accessorKey: "description", header: "Keterangan Aktivitas" },
  { id: "actions", header: "Detail", class: "w-20 text-right" },
];
</script>

<template>
  <div class="space-y-4">
    <!-- Top Bar / Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <span>Audit Log & Jejak Aktivitas</span>
          <UBadge
            :label="`${data?.data?.total || 0} Aktivitas`"
            color="primary"
            variant="subtle"
            size="xs"
            class="font-mono text-[11px]"
          />
        </h1>
      </div>

      <div v-if="activeFiltersCount > 0" class="flex items-center gap-2">
        <UButton
          label="Reset Filter"
          icon="i-lucide-filter-x"
          color="neutral"
          variant="ghost"
          size="sm"
          @click="resetFilters"
        />
      </div>
    </div>

    <!-- Quick Stats Cards (4 KPI Highlights) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <div class="p-3 rounded-lg border border-gray-200/80 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] flex items-center gap-3">
        <div class="size-9 rounded-md bg-primary-500/10 text-primary-600 dark:text-primary-400 flex items-center justify-center shrink-0">
          <UIcon name="i-lucide-activity" class="size-5" />
        </div>
        <div class="min-w-0">
          <span class="text-[11px] font-medium text-gray-500 dark:text-gray-400 block truncate">Total Aktivitas</span>
          <span class="text-lg font-bold text-gray-900 dark:text-white leading-tight">
            {{ data?.data?.total || 0 }}
          </span>
        </div>
      </div>

      <div class="p-3 rounded-lg border border-gray-200/80 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] flex items-center gap-3">
        <div class="size-9 rounded-md bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
          <UIcon name="i-lucide-route" class="size-5" />
        </div>
        <div class="min-w-0">
          <span class="text-[11px] font-medium text-gray-500 dark:text-gray-400 block truncate">Modul Terdaftar</span>
          <span class="text-lg font-bold text-gray-900 dark:text-white leading-tight">
            {{ data?.options?.modules?.length || 0 }} Modul
          </span>
        </div>
      </div>

      <div class="p-3 rounded-lg border border-gray-200/80 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] flex items-center gap-3">
        <div class="size-9 rounded-md bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
          <UIcon name="i-lucide-users" class="size-5" />
        </div>
        <div class="min-w-0">
          <span class="text-[11px] font-medium text-gray-500 dark:text-gray-400 block truncate">Aktor Terlibat</span>
          <span class="text-lg font-bold text-gray-900 dark:text-white leading-tight">
            {{ data?.options?.users?.length || 0 }} Akun
          </span>
        </div>
      </div>

      <div class="p-3 rounded-lg border border-gray-200/80 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] flex items-center gap-3">
        <div class="size-9 rounded-md bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
          <UIcon name="i-lucide-shield-alert" class="size-5" />
        </div>
        <div class="min-w-0">
          <span class="text-[11px] font-medium text-gray-500 dark:text-gray-400 block truncate">Tipe Peristiwa</span>
          <span class="text-lg font-bold text-gray-900 dark:text-white leading-tight">
            {{ data?.options?.events?.length || 0 }} Aksi
          </span>
        </div>
      </div>
    </div>

    <!-- ═══ RESPONSIVE FILTER COMPONENT ═══════════════════════════════════════ -->
    <AdminAuditLogsFilterBar
      v-model:search="search"
      v-model:module="selectedModule"
      v-model:event="selectedEvent"
      v-model:user-id="selectedUserId"
      v-model:date-from="dateFrom"
      v-model:date-to="dateTo"
      v-model:date-preset="selectedDatePreset"
      :module-options="moduleFilterItems"
      :event-options="eventFilterItems"
      :user-options="userFilterItems"
      :loading="loading"
      :active-filters-count="activeFiltersCount"
      :active-filter-chips="activeFilterChips"
      @refresh="() => refresh()"
      @reset="resetFilters"
    />

    <!-- Error State -->
    <div
      v-if="error"
      class="p-4 rounded-lg border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-950/30 text-red-700 dark:text-red-300 flex items-center justify-between"
    >
      <div class="flex items-center gap-2.5 text-sm">
        <UIcon name="i-lucide-alert-triangle" class="size-5 shrink-0" />
        <span>Gagal memuat data audit log. Pastikan Anda memiliki permission `audit-logs-view`.</span>
      </div>
      <UButton
        label="Coba Lagi"
        size="xs"
        color="error"
        variant="subtle"
        @click="() => refresh()"
      />
    </div>

    <!-- Main Data Table Card -->
    <div class="rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[#0b0f19] overflow-hidden">
      <!-- Loading Skeleton State -->
      <div v-if="loading" class="p-8 text-center space-y-3">
        <UIcon name="i-lucide-loader-2" class="size-6 animate-spin mx-auto text-primary-500" />
        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Memuat data log aktivitas...</p>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="!data?.data?.data || data.data.data.length === 0"
        class="p-16 text-center space-y-3"
      >
        <div class="size-12 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-400 mx-auto flex items-center justify-center">
          <UIcon name="i-lucide-history" class="size-6" />
        </div>
        <p class="text-sm font-semibold text-gray-900 dark:text-white">
          {{ activeFiltersCount > 0 ? "Tidak ada riwayat audit log yang cocok" : "Belum ada riwayat aktivitas" }}
        </p>
        <p class="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
          {{
            activeFiltersCount > 0
              ? "Cobalah ubah filter modul, event, kata kunci pencarian, atau rentang tanggal."
              : "Belum ada aktivitas yang tercatat dalam log sistem."
          }}
        </p>
        <div v-if="activeFiltersCount > 0" class="pt-2">
          <UButton
            label="Hapus Semua Filter"
            size="sm"
            color="neutral"
            variant="outline"
            @click="resetFilters"
          />
        </div>
      </div>

      <!-- Table Content -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
          <thead class="bg-gray-50 dark:bg-[#070b14] text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-white/[0.08]">
            <tr>
              <th scope="col" class="py-3 px-4 w-44 min-w-44 max-w-44">Waktu & Tanggal</th>
              <th scope="col" class="py-3 px-4 w-48">Pelaku (User)</th>
              <th scope="col" class="py-3 px-3 w-44">Modul</th>
              <th scope="col" class="py-3 px-3 w-28 text-center">Aksi</th>
              <th scope="col" class="py-3 px-3 min-w-[160px]">Target Data</th>
              <th scope="col" class="py-3 px-4">Keterangan Aktivitas</th>
              <th scope="col" class="py-3 px-4 w-20 text-right">Detail</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
            <tr
              v-for="row in data.data.data"
              :key="row.id"
              class="hover:bg-gray-50/60 dark:hover:bg-white/[0.02] transition-colors"
            >
              <!-- Waktu & Tanggal -->
              <td class="py-3 px-4 w-44 min-w-44 max-w-44 shrink-0">
                <div class="font-medium text-gray-900 dark:text-white flex items-center gap-1.5 whitespace-nowrap">
                  <UIcon name="i-lucide-calendar" class="size-3 text-gray-400 shrink-0" />
                  <span>{{ dayjs(row.created_at).format("DD MMM YYYY") }}</span>
                </div>
                <div class="text-[11px] text-gray-500 dark:text-gray-400 font-mono flex items-center gap-1.5 mt-0.5 whitespace-nowrap">
                  <UIcon name="i-lucide-clock" class="size-3 text-gray-400 shrink-0" />
                  <span>{{ dayjs(row.created_at).format("HH:mm:ss") }} WIB</span>
                </div>
              </td>

              <!-- Pelaku (User) -->
              <td class="py-3 px-4 w-48">
                <div class="min-w-0">
                  <div class="font-medium text-xs text-gray-900 dark:text-white truncate">
                    {{ row.user?.name || 'Sistem / Worker' }}
                  </div>
                  <div class="text-[10px] text-gray-500 dark:text-gray-400 font-mono truncate">
                    {{ row.user?.email || 'automated' }}
                  </div>
                </div>
              </td>

              <!-- Modul Spasial -->
              <td class="py-3 px-3 w-44">
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium border"
                  :class="getModuleMeta(row.module).badgeClass"
                >
                  <span>{{ getModuleMeta(row.module).label }}</span>
                </span>
              </td>

              <!-- Aksi / Event -->
              <td class="py-3 px-3 text-center w-28">
                <UBadge
                  :label="getEventBadge(row.event).label"
                  :color="getEventBadge(row.event).color"
                  variant="subtle"
                  size="xs"
                  class="font-mono text-[10px] px-2 py-0.5 uppercase tracking-wider"
                />
              </td>

              <!-- Target Data -->
              <td class="py-3 px-3 min-w-[160px]">
                <div class="font-semibold text-xs text-gray-900 dark:text-gray-100 truncate max-w-[200px]" :title="row.target_label || ''">
                  {{ row.target_label || '-' }}
                </div>
                <div v-if="row.auditable_id" class="text-[10px] text-gray-400 font-mono truncate">
                  ID: {{ row.auditable_id }}
                </div>
              </td>

              <!-- Keterangan Aktivitas -->
              <td class="py-3 px-4">
                <div class="text-xs text-gray-600 dark:text-gray-300 line-clamp-1" :title="row.description">
                  {{ row.description }}
                </div>
              </td>

              <!-- Tombol Detail -->
              <td class="py-3 px-4 text-right w-20">
                <UButton
                  label="Detail"
                  icon="i-lucide-arrow-up-right"
                  size="xs"
                  color="neutral"
                  variant="subtle"
                  class="cursor-pointer hover:border-primary-500 hover:text-primary-600 dark:hover:text-primary-400"
                  @click="openDetail(row)"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div
        v-if="!loading && (data?.data?.total ?? 0) > 0"
        class="p-4 border-t border-gray-200 dark:border-white/[0.08] flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-500 dark:text-gray-400"
      >
        <div>
          Menampilkan baris {{ ((data?.data?.current_page ?? page) - 1) * (data?.data?.per_page ?? 15) + 1 }} sampai
          {{ Math.min((data?.data?.current_page ?? page) * (data?.data?.per_page ?? 15), data?.data?.total ?? 0) }} dari total
          <strong class="text-gray-800 dark:text-gray-200">{{ data?.data?.total ?? 0 }}</strong> aktivitas
        </div>
        <UPagination
          v-model:page="page"
          :total="data?.data?.total || 0"
          :items-per-page="data?.data?.per_page || 15"
          size="sm"
        />
      </div>
    </div>

    <!-- ═══ MODAL DETAIL LOG & DIFF INSPECTOR ═══════════════════════════════════ -->
    <UModal
      v-model:open="isDetailOpen"
      :title="`Log Aktivitas #${activeLog?.id || ''}`"
      :description="activeLog?.description || 'Perbandingan dan inspeksi perubahan data audit.'"
      :ui="{ content: 'dark:bg-[#0b0f19] dark:border-white/[0.08] max-w-4xl lg:max-w-5xl' }"
    >
      <template #body>
        <div v-if="activeLog" class="space-y-4">
          <!-- Overview Cards (Header Grid) -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-3 rounded-lg bg-gray-50/80 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800 text-xs">
            <div>
              <span class="text-gray-500 dark:text-gray-400 block text-[11px] font-medium">Modul</span>
              <div class="flex items-center gap-1.5 mt-0.5">
                <UIcon :name="getModuleMeta(activeLog.module).icon" class="size-3.5 text-primary-500" />
                <span class="font-semibold text-gray-900 dark:text-white">{{ formatModuleName(activeLog.module) }}</span>
              </div>
            </div>

            <div>
              <span class="text-gray-500 dark:text-gray-400 block text-[11px] font-medium">Event / Aksi</span>
              <div class="mt-0.5">
                <UBadge
                  :label="getEventBadge(activeLog.event).label"
                  :color="getEventBadge(activeLog.event).color"
                  variant="subtle"
                  size="xs"
                  class="font-mono text-[10px]"
                />
              </div>
            </div>

            <div>
              <span class="text-gray-500 dark:text-gray-400 block text-[11px] font-medium">Pelaku Aksi</span>
              <span class="font-semibold text-gray-900 dark:text-white block truncate mt-0.5">
                {{ activeLog.user?.name || 'Sistem' }}
              </span>
            </div>

            <div>
              <span class="text-gray-500 dark:text-gray-400 block text-[11px] font-medium">Waktu Transaksi</span>
              <span class="font-mono text-[11px] text-gray-700 dark:text-gray-300 block mt-0.5">
                {{ dayjs(activeLog.created_at).format("DD/MM/YYYY HH:mm:ss") }}
              </span>
            </div>
          </div>

          <!-- Network & Client Info -->
          <div class="flex flex-wrap items-center justify-between text-[11px] text-gray-500 dark:text-gray-400 px-1 py-1 border-b border-gray-100 dark:border-gray-800/60 gap-2">
            <div class="flex items-center gap-4">
              <span v-if="activeLog.ip_address" class="flex items-center gap-1">
                <UIcon name="i-lucide-globe" class="size-3.5 text-gray-400" />
                <span class="font-mono">IP: {{ activeLog.ip_address }}</span>
              </span>
              <span v-if="activeLog.auditable_type" class="flex items-center gap-1">
                <UIcon name="i-lucide-database" class="size-3.5 text-gray-400" />
                <span class="font-mono">{{ activeLog.auditable_type }} ({{ activeLog.auditable_id }})</span>
              </span>
            </div>

          </div>

          <!-- Nuxt UI Tabs Component -->
          <UTabs
            v-model="detailTab"
            :items="detailTabs"
            variant="pill"
            color="neutral"
            size="xs"
            class="w-full"
            :ui="{
              list: 'w-full sm:w-fit',
            }"
          >
            <!-- TAB 1: Diff / Snapshot Inspector -->
            <template #diff>
              <div class="space-y-3 pt-2">
                <!-- Context Header Toolbar -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 p-2.5 rounded-lg bg-gray-50/80 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800">
                  <!-- Left side: Context badge & title -->
                  <div class="flex items-center gap-2 flex-wrap">
                <span
                  v-if="isDeleteEvent"
                  class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-800/40"
                >
                  <UIcon name="i-lucide-trash-2" class="size-3.5" />
                  Data Dihapus
                </span>
                <span
                  v-else-if="isCreateEvent"
                  class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border border-blue-200 dark:border-blue-800/40"
                >
                  <UIcon name="i-lucide-plus-circle" class="size-3.5" />
                  Data Dibuat
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-800/40"
                >
                  <UIcon name="i-lucide-pen-line" class="size-3.5" />
                  Data Dimodifikasi
                </span>

                <span class="text-xs font-semibold text-gray-900 dark:text-gray-100">
                  <template v-if="isDeleteEvent">
                    Snapshot Atribut Sebelum Dihapus
                  </template>
                  <template v-else-if="isCreateEvent">
                    Atribut Record Baru yang Disimpan
                  </template>
                  <template v-else>
                    Perbandingan Perubahan Data
                  </template>
                </span>

                <span
                  v-if="isUpdateEvent"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20"
                >
                  <span class="size-1.5 rounded-full bg-amber-500" />
                  {{ changedDiffCount }} dari {{ totalDiffCount }} atribut diubah
                </span>
                <span v-else class="text-[11px] text-gray-500 dark:text-gray-400">
                  ({{ filteredDiffRows.length }} atribut)
                </span>
              </div>

              <!-- Right side: Filters (only shown if update event) -->
              <div v-if="isUpdateEvent" class="flex items-center gap-2">
                <div class="inline-flex p-0.5 rounded-md bg-gray-200/70 dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700/60 text-[11px]">
                  <button
                    type="button"
                    class="px-2.5 py-1 rounded font-medium transition-colors cursor-pointer"
                    :class="diffFilterMode === 'changed' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                    @click="diffFilterMode = 'changed'"
                  >
                    Berubah ({{ changedDiffCount }})
                  </button>
                  <button
                    type="button"
                    class="px-2.5 py-1 rounded font-medium transition-colors cursor-pointer"
                    :class="diffFilterMode === 'all' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                    @click="diffFilterMode = 'all'"
                  >
                    Semua ({{ totalDiffCount }})
                  </button>
                </div>
              </div>
            </div>

            <!-- Nuxt UI 4 UTable Component -->
            <div v-if="filteredDiffRows.length > 0" class="border border-gray-200/80 dark:border-white/[0.08] rounded-lg overflow-hidden bg-white dark:bg-[#0b0f19]">
              <UTable
                :data="filteredDiffRows"
                :columns="diffTableColumns"
                :ui="{
                  th: 'py-1.5 px-3 text-[11px] font-semibold text-gray-700 dark:text-gray-300',
                  td: 'py-1.5 px-3 text-xs align-top',
                }"
                class="max-h-[480px] overflow-y-auto"
              >
                <!-- Atribut (key) cell -->
                <template #key-cell="{ row }">
                  <div class="flex items-center gap-1.5 flex-wrap font-mono">
                    <span
                      v-if="isUpdateEvent && row.original.isChanged"
                      class="size-1.5 rounded-full bg-amber-500 shrink-0"
                      title="Atribut diubah"
                    />
                    <span class="font-semibold text-gray-900 dark:text-gray-100 text-xs">{{ row.original.key }}</span>
                    <UBadge
                      v-if="row.original.isSpatial"
                      label="Spasial"
                      color="primary"
                      variant="subtle"
                      size="xs"
                      class="text-[9px] px-1 py-0"
                    />
                  </div>
                </template>

                <!-- Nilai Sebelum (before) cell -->
                <template #before-cell="{ row }">
                  <div
                    class="text-xs font-mono"
                    :class="isUpdateEvent && row.original.isChanged ? 'text-rose-700 dark:text-rose-300 font-medium' : 'text-gray-700 dark:text-gray-300'"
                  >
                    <!-- Spatial Value -->
                    <div v-if="row.original.isSpatial && row.original.before">
                      <div class="flex items-center gap-2">
                        <span>{{ row.original.spatialSummary || 'Data Geometri' }}</span>
                        <UButton
                          :label="expandedSpatialKeys[`before_${row.original.key}`] ? 'Tutup' : 'GeoJSON'"
                          size="xs"
                          variant="ghost"
                          color="neutral"
                          class="text-[10px] p-0 h-auto"
                          @click="toggleSpatialExpand(`before_${row.original.key}`)"
                        />
                      </div>
                      <pre
                        v-if="expandedSpatialKeys[`before_${row.original.key}`]"
                        class="mt-1 p-2 rounded bg-gray-900 text-gray-100 text-[10px] overflow-x-auto max-h-36 whitespace-pre-wrap"
                      >{{ formatDiffValue(row.original.before) }}</pre>
                    </div>

                    <!-- Null / Empty -->
                    <span v-else-if="row.original.before === null || row.original.before === undefined" class="text-gray-400 italic font-mono">
                      null
                    </span>

                    <!-- Boolean -->
                    <UBadge
                      v-else-if="typeof row.original.before === 'boolean'"
                      :label="row.original.before ? 'true' : 'false'"
                      :color="row.original.before ? 'success' : 'error'"
                      variant="subtle"
                      size="xs"
                    />

                    <!-- Text / Number -->
                    <div v-else class="whitespace-pre-wrap break-words leading-snug">
                      {{ formatDiffValue(row.original.before) }}
                    </div>
                  </div>
                </template>

                <!-- Nilai Sesudah (after) cell -->
                <template #after-cell="{ row }">
                  <div
                    class="text-xs font-mono"
                    :class="isUpdateEvent && row.original.isChanged ? 'text-blue-700 dark:text-blue-300 font-medium' : 'text-gray-800 dark:text-gray-200'"
                  >
                    <!-- Spatial Value -->
                    <div v-if="row.original.isSpatial && row.original.after">
                      <div class="flex items-center gap-2">
                        <span>{{ row.original.spatialSummary || 'Data Geometri' }}</span>
                        <UButton
                          :label="expandedSpatialKeys[`after_${row.original.key}`] ? 'Tutup' : 'GeoJSON'"
                          size="xs"
                          variant="ghost"
                          color="neutral"
                          class="text-[10px] p-0 h-auto"
                          @click="toggleSpatialExpand(`after_${row.original.key}`)"
                        />
                      </div>
                      <pre
                        v-if="expandedSpatialKeys[`after_${row.original.key}`]"
                        class="mt-1 p-2 rounded bg-gray-900 text-gray-100 text-[10px] overflow-x-auto max-h-36 whitespace-pre-wrap"
                      >{{ formatDiffValue(row.original.after) }}</pre>
                    </div>

                    <!-- Null / Empty -->
                    <span v-else-if="row.original.after === null || row.original.after === undefined" class="text-gray-400 italic font-mono">
                      null
                    </span>

                    <!-- Boolean -->
                    <UBadge
                      v-else-if="typeof row.original.after === 'boolean'"
                      :label="row.original.after ? 'true' : 'false'"
                      :color="row.original.after ? 'success' : 'error'"
                      variant="subtle"
                      size="xs"
                    />

                    <!-- Text / Number -->
                    <div v-else class="whitespace-pre-wrap break-words leading-snug">
                      {{ formatDiffValue(row.original.after) }}
                    </div>
                  </div>
                </template>

                <!-- Status cell (only for update event) -->
                <template #status-cell="{ row }">
                  <div class="text-center">
                    <UBadge
                      v-if="row.original.isChanged"
                      label="Diubah"
                      color="warning"
                      variant="subtle"
                      size="xs"
                    />
                    <span v-else class="text-gray-400 text-[10px]">Tetap</span>
                  </div>
                </template>
              </UTable>
            </div>

            <!-- Empty State for Filtered Diff Rows -->
            <div
              v-else
              class="p-8 text-center rounded-lg border border-dashed border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/30"
            >
              <UIcon name="i-lucide-filter-x" class="size-8 text-gray-400 mx-auto mb-2" />
              <p class="text-xs font-medium text-gray-700 dark:text-gray-300">
                <template v-if="diffFilterMode === 'changed'">
                  Tidak ada atribut yang mengalami perubahan pada aktivitas ini.
                </template>
                <template v-else>
                  Tidak ada data atribut yang dicatat pada aktivitas ini.
                </template>
              </p>
              <div v-if="diffFilterMode === 'changed'" class="mt-3 flex items-center justify-center gap-2">
                <UButton
                  label="Tampilkan Semua Atribut"
                  size="xs"
                  color="neutral"
                  variant="outline"
                  @click="diffFilterMode = 'all'"
                />
              </div>
            </div>

            <!-- Extra Metadata Card -->
            <div v-if="activeLog.meta && Object.keys(activeLog.meta).length > 0" class="p-3 rounded-lg border border-gray-200/80 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-900/40 text-xs">
              <div class="flex items-center gap-1.5 mb-1 text-gray-700 dark:text-gray-300 font-semibold">
                <UIcon name="i-lucide-info" class="size-3.5 text-primary-500" />
                <span>Metadata Tambahan:</span>
              </div>
              <pre class="font-mono text-[11px] text-gray-600 dark:text-gray-400 whitespace-pre-wrap overflow-x-auto">{{ JSON.stringify(activeLog.meta, null, 2) }}</pre>
            </div>
              </div>
            </template>

            <!-- TAB 2: Raw JSON View -->
            <template #raw>
              <div class="space-y-2 pt-2">
                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 px-1">
                  <span>Struktur Utuh Audit Record JSON:</span>
                  <UButton
                    label="Salin JSON"
                    icon="i-lucide-copy"
                    size="xs"
                    color="neutral"
                    variant="outline"
                    @click="copyJsonPayload"
                  />
                </div>
                <pre class="p-3.5 rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-900 text-gray-100 font-mono text-[11px] overflow-x-auto max-h-96">{{ JSON.stringify(activeLog, null, 2) }}</pre>
              </div>
            </template>
          </UTabs>

          <!-- User Agent Footer -->
          <div v-if="activeLog.user_agent" class="text-[10px] text-gray-400 dark:text-gray-500 font-mono truncate px-1 flex items-center gap-1.5">
            <UIcon name="i-lucide-monitor" class="size-3 shrink-0" />
            <span class="truncate">{{ activeLog.user_agent }}</span>
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex items-center justify-between w-full">
          <UButton
            label="Salin Data"
            icon="i-lucide-clipboard"
            size="sm"
            color="neutral"
            variant="ghost"
            @click="copyJsonPayload"
          />
          <UButton
            label="Tutup"
            size="sm"
            color="neutral"
            variant="outline"
            @click="isDetailOpen = false"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { DropdownMenuItem } from "@nuxt/ui";

interface Props {
  state?: "collapsed" | "expanded";
}

const props = withDefaults(defineProps<Props>(), {
  state: "expanded",
});

const isCollapsed = computed(() => props.state === "collapsed");
const auth = useAuthStore();
const colorMode = useColorMode();
const config = useRuntimeConfig();

function computeInitials(name?: string | null): string {
  if (!name) return "OP";
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

const user = computed(() => {
  const displayName = auth.user?.name || "Operator Melarosa";
  return {
    name: displayName,
    initials: computeInitials(displayName),
    src: auth.user?.avatar ? `${config.public.apiBase}/storage/${auth.user.avatar}` : undefined,
    alt: displayName,
    subtitle: auth.user?.desa?.nama_desa
      ? `Desa ${auth.user.desa.nama_desa}`
      : auth.user?.kecamatan?.nama_kecamatan
        ? `Kec. ${auth.user.kecamatan.nama_kecamatan}`
        : auth.user?.email || "Pemkab Bojonegoro",
  };
});

const userItems = computed<DropdownMenuItem[][]>(() => [
  [
    {
      label: user.value.name,
      description: auth.user?.email || user.value.subtitle,
      icon: "i-lucide-user",
    },
  ],
  [
    {
      label: "Profil Akun",
      icon: "i-lucide-user-cog",
      to: "/admin/account/general",
    },
    {
      label: "Perangkat Terhubung",
      icon: "i-lucide-smartphone",
      to: "/admin/account/devices",
    },
  ],
  [
    {
      label: "Mode Tampilan",
      icon: "i-lucide-sun-moon",
      children: [
        {
          label: "Mode Terang",
          icon: "i-lucide-sun",
          type: "checkbox",
          checked: colorMode.value === "light",
          onUpdateChecked(checked: boolean) {
            if (checked) {
              colorMode.preference = "light";
            }
          },
          onSelect(e: Event) {
            e.preventDefault();
          },
        },
        {
          label: "Mode Gelap",
          icon: "i-lucide-moon",
          type: "checkbox",
          checked: colorMode.value === "dark",
          onUpdateChecked(checked: boolean) {
            if (checked) {
              colorMode.preference = "dark";
            }
          },
          onSelect(e: Event) {
            e.preventDefault();
          },
        },
      ],
    },
  ],
  [
    {
      label: "Portal Publik",
      icon: "i-lucide-external-link",
      to: "/",
      target: "_blank",
      exact: true,
      active: false,
    },
    {
      label: "Keluar Sesi",
      icon: "i-lucide-log-out",
      color: "error" as const,
      onSelect() {
        auth.logout();
      },
    },
  ],
]);
</script>

<template>
  <UDropdownMenu
    :items="userItems"
    :content="{ align: isCollapsed ? 'center' : 'start', collisionPadding: 12 }"
    :ui="{ content: 'w-60 dark:bg-[#0f172a] dark:border-slate-800' }"
    :class="isCollapsed ? 'w-full flex justify-center' : 'w-full'"
  >
    <UButton
      color="neutral"
      variant="ghost"
      :square="isCollapsed"
      aria-label="Menu akun operator"
      :class="[
        'overflow-hidden transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:focus-visible:outline-blue-400',
        isCollapsed
          ? 'size-11 p-0 flex items-center justify-center rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800/70'
          : 'w-full min-h-11 flex items-center justify-between px-2.5 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800/70',
      ]"
    >
      <div class="flex items-center gap-2.5 min-w-0">
        <UAvatar
          :src="user.src"
          :text="user.initials"
          :alt="user.alt"
          size="sm"
          class="shrink-0 size-8 bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300 font-semibold"
        />
        <div v-if="!isCollapsed" class="flex flex-col text-left min-w-0">
          <span class="truncate font-semibold text-sm text-slate-900 dark:text-slate-50 leading-tight">
            {{ user.name }}
          </span>
          <ClientOnly>
            <span class="truncate text-xs text-slate-600 dark:text-slate-400 leading-tight mt-0.5">
              {{ user.subtitle }}
            </span>
            <template #fallback>
              <span class="truncate text-xs text-transparent select-none leading-tight mt-0.5">&nbsp;</span>
            </template>
          </ClientOnly>
        </div>
      </div>
      <UIcon
        v-if="!isCollapsed"
        name="i-lucide-chevrons-up-down"
        class="size-4 text-slate-500 dark:text-slate-400 shrink-0 ms-auto"
      />
    </UButton>
  </UDropdownMenu>
</template>

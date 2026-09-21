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

const user = computed(() => ({
  name: auth.user.name || "Administrator",
  avatar: {
    src: auth.user.avatar
      ? config.public.apiBase + "/storage/" + auth.user.avatar
      : "https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=96&h=96",
    alt: auth.user.name || "Admin",
  },
}));

const userItems = computed<DropdownMenuItem[][]>(() => [
  [
    {
      label: auth.user.name || "Administrator",
      description: auth.user.email,
      icon: "i-lucide-user",
    },
  ],
  [
    {
      label: "Account Profile",
      icon: "i-lucide-user",
      to: "/admin/account/general",
    },
    {
      label: "Connected Devices",
      icon: "i-lucide-smartphone",
      to: "/admin/account/devices",
    },
  ],
  [
    {
      label: "Appearance",
      icon: "i-lucide-sun-moon",
      children: [
        {
          label: "Light",
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
          label: "Dark",
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
      label: "Public Site",
      icon: "i-lucide-external-link",
      to: "/",
    },
    {
      label: "Log out",
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
    :ui="{ content: 'w-56 dark:bg-[#0f1422] dark:border-gray-800' }"
    :class="isCollapsed ? 'w-full flex justify-center' : 'w-full'"
  >
    <UButton
      color="neutral"
      variant="ghost"
      :square="isCollapsed"
      aria-label="User account menu"
      :class="[
        'overflow-hidden transition-colors',
        isCollapsed
          ? 'size-9 p-0 flex items-center justify-center rounded-md hover:bg-gray-100 dark:hover:bg-gray-800/60'
          : 'w-full flex items-center justify-between px-2.5 py-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800/60',
      ]"
    >
      <div class="flex items-center gap-2.5 min-w-0">
        <UAvatar
          :src="user.avatar.src"
          :alt="user.avatar.alt"
          size="sm"
          class="shrink-0 size-8"
        />
        <div v-if="!isCollapsed" class="flex flex-col text-left min-w-0">
          <span class="truncate font-semibold text-sm text-gray-900 dark:text-gray-100 leading-tight">
            {{ user.name }}
          </span>
          <span class="truncate text-xs text-gray-500 dark:text-gray-400 leading-tight mt-0.5">
            {{ auth.user.email }}
          </span>
        </div>
      </div>
      <UIcon
        v-if="!isCollapsed"
        name="i-lucide-chevrons-up-down"
        class="size-4 text-gray-400 dark:text-gray-500 shrink-0 ms-auto"
      />
    </UButton>
  </UDropdownMenu>
</template>

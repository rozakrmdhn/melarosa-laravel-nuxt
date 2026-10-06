<script setup lang="ts">
import type { DropdownMenuItem } from "@nuxt/ui";

interface Props {
  state?: "collapsed" | "expanded";
}

const props = withDefaults(defineProps<Props>(), {
  state: "expanded",
});

const isCollapsed = computed(() => props.state === "collapsed");

const teams = ref([
  {
    label: "Melarosa",
    icon: "i-simple-icons-nuxt",
    badge: "Enterprise",
  },
  {
    label: "Production DB",
    icon: "i-lucide-database",
    badge: "PostgreSQL",
  },
]);

const selectedTeam = ref(teams.value[0]);

const teamsItems = computed<DropdownMenuItem[][]>(() => [
  teams.value.map((team, index) => ({
    ...team,
    kbds: ["meta", String(index + 1)],
    onSelect() {
      selectedTeam.value = team;
    },
  })),
  [
    {
      label: "Create workspace",
      icon: "i-lucide-circle-plus",
    },
  ],
]);
</script>

<template>
  <UDropdownMenu
    :items="teamsItems"
    :content="{ align: isCollapsed ? 'center' : 'start', collisionPadding: 12 }"
    :ui="{ content: 'w-56 dark:bg-[#0f1422] dark:border-gray-800' }"
    :class="isCollapsed ? 'w-full flex justify-center' : 'flex-1 min-w-0'"
  >
    <UButton
      color="neutral"
      variant="ghost"
      :square="isCollapsed"
      aria-label="Select workspace"
      :class="[
        'overflow-hidden transition-colors',
        isCollapsed
          ? 'size-9 p-0 flex items-center justify-center rounded-md hover:bg-gray-100 dark:hover:bg-gray-800/60'
          : 'w-full flex items-center justify-between px-2.5 py-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800/60',
      ]"
    >
      <div class="flex items-center gap-2.5 min-w-0">
        <div class="size-8 rounded-md bg-blue-500/10 dark:bg-blue-500/15 flex items-center justify-center shrink-0">
          <UIcon
            name="i-simple-icons-nuxt"
            class="size-4.5 text-blue-600 dark:text-blue-400 shrink-0"
          />
        </div>
        <div v-if="!isCollapsed" class="flex flex-col text-left min-w-0">
          <span class="truncate font-semibold text-sm text-gray-900 dark:text-gray-100 leading-tight">
            {{ selectedTeam.label }}
          </span>
          <span class="truncate text-xs text-gray-500 dark:text-gray-400 leading-tight mt-0.5">
            Enterprise Workspace
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

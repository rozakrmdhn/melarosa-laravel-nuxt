<script lang="ts" setup>
const dayjs = useDayjs();
const auth = useAuthStore();

const { data, status, refresh } = useHttp<any>("devices");
const loading = computed(() => status.value === "pending");

const columns = [
  {
    accessorKey: "name",
    header: "Device",
  },
  {
    accessorKey: "last_used_at",
    header: "Last active",
    class: "max-w-[9rem] w-[9rem] min-w-[9rem]",
  },
  {
    id: "actions",
  },
];

const items = (row: any) => [
  [
    {
      label: "Disconnect Session",
      icon: "i-heroicons-trash-20-solid",
      color: "error" as const,
      onSelect: async () => {
        await $http("devices/disconnect", {
          method: "POST",
          body: {
            key: row.key,
          },
          async onFetchResponse({ response }) {
            if (response._data?.ok) {
              await refresh();
              await auth.fetchUser();
            }
          },
        });
      },
    },
  ],
];

useSeoMeta({
  title: "Connected Devices - Admin",
});
</script>

<template>
  <UCard
    :ui="{
      root: 'bg-white dark:bg-[#0b0f19] border border-gray-200/70 dark:border-white/[0.08] ring-0 rounded-lg overflow-hidden shadow-none',
      body: 'p-0 sm:p-0',
    }"
  >
    <div class="p-5 border-b border-gray-100 dark:border-white/[0.08] flex items-center justify-between">
      <div>
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
          Active Sessions & Devices
        </h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
          Inspect browser and mobile sessions currently authorized to your account.
        </p>
      </div>
    </div>

    <UTable
      :data="data?.devices || []"
      :columns="columns"
      size="md"
      :loading="loading"
      loading-color="primary"
    >
      <template #name-cell="{ row }">
        <div class="flex items-center gap-2.5 py-1">
          <div class="size-8 rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
            <UIcon name="i-lucide-smartphone" class="size-4" />
          </div>
          <div>
            <div class="font-semibold text-sm text-gray-900 dark:text-white flex items-center gap-1.5">
              {{ row.original.name }}
              <UBadge
                v-if="row.original.is_current as boolean"
                label="Current Session"
                color="primary"
                variant="subtle"
                size="xs"
              />
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">
              IP: {{ row.original.ip }}
            </div>
          </div>
        </div>
      </template>

      <template #last_used_at-cell="{ row }">
        <span class="text-xs text-gray-500 dark:text-gray-400">
          {{ dayjs(row.original.last_used_at as string).fromNow() }}
        </span>
      </template>

      <template #actions-cell="{ row }">
        <div class="flex justify-end pe-2">
          <UDropdownMenu :items="items(row.original)" :content="{ side: 'bottom', align: 'end' }">
            <UButton
              :disabled="row.original.is_current as boolean"
              color="neutral"
              variant="ghost"
              size="xs"
              icon="i-heroicons-ellipsis-horizontal-20-solid"
              aria-label="Device actions"
            />
          </UDropdownMenu>
        </div>
      </template>

      <template #empty>
        <div class="text-center py-10 text-sm text-gray-500 dark:text-gray-400">
          <UIcon name="i-lucide-smartphone" class="size-7 mx-auto mb-2 opacity-50" />
          <p class="font-medium">No connected devices found</p>
        </div>
      </template>
    </UTable>
  </UCard>
</template>

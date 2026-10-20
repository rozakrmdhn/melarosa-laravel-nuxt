<script setup lang="ts">
import type { StatusBkkdProposal, HasilVerifikasiBkkd } from '~/types/bkkd';

const props = defineProps<{
  status?: StatusBkkdProposal | HasilVerifikasiBkkd | string;
  size?: 'xs' | 'sm' | 'md';
}>();

const config = computed(() => {
  switch (props.status) {
    case 'draft':
      return { label: 'Draft', color: 'neutral' as const, icon: 'i-lucide-file-edit' };
    case 'diajukan_opd':
      return { label: 'Diajukan ke OPD', color: 'info' as const, icon: 'i-lucide-send' };
    case 'dikembalikan':
      return { label: 'Dikembalikan', color: 'warning' as const, icon: 'i-lucide-undo' };
    case 'layak':
      return { label: 'Layak', color: 'success' as const, icon: 'i-lucide-check-circle-2' };
    case 'layak_bersyarat':
      return { label: 'Layak Bersyarat', color: 'primary' as const, icon: 'i-lucide-alert-circle' };
    case 'tidak_layak':
      return { label: 'Tidak Layak', color: 'error' as const, icon: 'i-lucide-x-circle' };
    case 'ditetapkan_tapd':
      return { label: 'Ditetapkan TAPD', color: 'success' as const, icon: 'i-lucide-award' };
    case 'tidak_terpilih':
      return { label: 'Tidak Terpilih TAPD', color: 'neutral' as const, icon: 'i-lucide-minus-circle' };
    case 'digantikan_papbd':
      return { label: 'Digantikan di P-APBD', color: 'warning' as const, icon: 'i-lucide-arrow-right-left' };
    case 'dibatalkan':
      return { label: 'Dibatalkan', color: 'error' as const, icon: 'i-lucide-ban' };
    default:
      return { label: props.status || 'Tidak Diketahui', color: 'neutral' as const, icon: 'i-lucide-help-circle' };
  }
});
</script>

<template>
  <UBadge
    :color="config.color"
    :size="size === 'xs' ? 'xs' : (size === 'md' ? 'md' : 'sm')"
    variant="subtle"
    class="inline-flex items-center font-semibold whitespace-nowrap border border-current/20 shadow-2xs transition-all"
    :class="[
      size === 'xs'
        ? 'gap-1 px-2 py-0.5 text-[11px]'
        : size === 'md'
          ? 'gap-2 px-3 py-1.5 text-xs'
          : 'gap-1.5 px-2.5 py-1 text-xs'
    ]"
  >
    <UIcon
      :name="config.icon"
      class="shrink-0"
      :class="size === 'xs' ? 'w-3 h-3' : 'w-3.5 h-3.5'"
    />
    <span>{{ config.label }}</span>
  </UBadge>
</template>

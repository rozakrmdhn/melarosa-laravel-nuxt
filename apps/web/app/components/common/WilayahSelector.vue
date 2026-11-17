<script setup lang="ts">
import { useAuthStore } from '~/stores/auth';
import { useWilayahStore } from '~/stores/wilayah';

const ALL_OPTION_ID = '__ALL__';

interface Props {
  modelKecamatan?: number | null;
  modelDesa?: number | null;
  disabled?: boolean;
  showLabels?: boolean;
  required?: boolean;
  layout?: 'row' | 'col' | 'inline';
  allOptionLabel?: string;
  allowAll?: boolean;
  showClear?: boolean;
  size?: 'xs' | 'sm' | 'md';
}

const props = withDefaults(defineProps<Props>(), {
  modelKecamatan: null,
  modelDesa: null,
  disabled: false,
  showLabels: true,
  required: false,
  layout: 'row',
  allOptionLabel: 'Semua',
  allowAll: false,
  showClear: true,
  size: 'sm',
});

const emit = defineEmits<{
  (e: 'update:modelKecamatan', val: number | null): void;
  (e: 'update:model-kecamatan', val: number | null): void;
  (e: 'update:modelDesa', val: number | null): void;
  (e: 'update:model-desa', val: number | null): void;
  (e: 'change', val: { id_kecamatan: number | null; id_desa: number | null }): void;
}>();

const auth = useAuthStore();
const wilayahStore = useWilayahStore();
const { isAdmin } = usePermission();

const rawKecamatanList = computed(() => wilayahStore.kecamatanList);
const rawDesaList = computed(() => {
  if (!props.modelKecamatan) return [];
  return wilayahStore.desaByKecamatan[Number(props.modelKecamatan)] || [];
});

const loadingKecamatan = computed(() => wilayahStore.loadingKecamatan);
const loadingDesa = computed(() => wilayahStore.isDesaLoading(props.modelKecamatan));

const isUnrestrictedRole = computed(
  () => isAdmin() || auth.hasRole('admin') || auth.hasRole('verifierBappeda'),
);

// Auto-lock logic berdasarkan role & wilayah pengguna
const isKecamatanLocked = computed(() => {
  if (props.disabled) return true;
  if (isUnrestrictedRole.value) return false;
  return !!auth.user?.id_kecamatan;
});

const isDesaLocked = computed(() => {
  if (props.disabled) return true;
  if (isUnrestrictedRole.value) return false;
  return !!auth.user?.id_desa;
});

const kecamatanItems = computed(() => {
  const items = rawKecamatanList.value.map((k) => ({
    id: k.id as number | string,
    label: k.nama_kecamatan,
  }));
  if (props.allowAll) {
    return [{ id: ALL_OPTION_ID as number | string, label: `${props.allOptionLabel} Kecamatan` }, ...items];
  }
  return items;
});

const desaItems = computed(() => {
  const items = rawDesaList.value.map((d) => ({
    id: d.id as number | string,
    label: d.nama_desa,
  }));
  if (props.allowAll) {
    return [{ id: ALL_OPTION_ID as number | string, label: `${props.allOptionLabel} Desa` }, ...items];
  }
  return items;
});

const selectedKecamatanValue = computed(() => {
  if (props.modelKecamatan !== null && props.modelKecamatan !== undefined) {
    return props.modelKecamatan;
  }
  return props.allowAll ? ALL_OPTION_ID : undefined;
});

const selectedDesaValue = computed(() => {
  if (props.modelDesa !== null && props.modelDesa !== undefined) {
    return props.modelDesa;
  }
  return props.allowAll ? ALL_OPTION_ID : undefined;
});

function resolveId(val: any): number | null {
  if (val === null || val === undefined) return null;
  if (typeof val === 'object') {
    val = val.id ?? val.value ?? null;
  }
  if (val === null || val === undefined || val === '' || val === 'ALL' || val === ALL_OPTION_ID) {
    return null;
  }
  const n = Number(val);
  return isNaN(n) ? null : n;
}

function handleKecamatanSelect(val: any) {
  const newKec = resolveId(val);
  emit('update:modelKecamatan', newKec);
  emit('update:model-kecamatan', newKec);
  if (!isDesaLocked.value) {
    emit('update:modelDesa', null);
    emit('update:model-desa', null);
    emit('change', { id_kecamatan: newKec, id_desa: null });
  } else {
    emit('change', { id_kecamatan: newKec, id_desa: props.modelDesa });
  }
}

function handleDesaSelect(val: any) {
  const newDesa = resolveId(val);
  emit('update:modelDesa', newDesa);
  emit('update:model-desa', newDesa);
  emit('change', { id_kecamatan: props.modelKecamatan, id_desa: newDesa });
}

async function loadKecamatan() {
  await wilayahStore.getKecamatanList();
  if (!isUnrestrictedRole.value && auth.user?.id_kecamatan && !props.modelKecamatan) {
    emit('update:modelKecamatan', auth.user.id_kecamatan);
    emit('update:model-kecamatan', auth.user.id_kecamatan);
  }
}

async function loadDesa(kecId?: number | null) {
  if (!kecId) return;
  await wilayahStore.getDesaList(kecId);
  if (!isUnrestrictedRole.value && auth.user?.id_desa && !props.modelDesa) {
    emit('update:modelDesa', auth.user.id_desa);
    emit('update:model-desa', auth.user.id_desa);
  }
}

watch(
  () => props.modelKecamatan,
  (newKec) => {
    loadDesa(newKec);
  },
  { immediate: true },
);

onMounted(() => {
  loadKecamatan();
});

const isKecamatanActive = computed(
  () => !isKecamatanLocked.value && props.modelKecamatan !== null && props.modelKecamatan !== undefined,
);
const isDesaActive = computed(
  () => !isDesaLocked.value && props.modelDesa !== null && props.modelDesa !== undefined,
);

const dropdownContentProps = {
  align: 'start' as const,
  sideOffset: 4,
};

const formSelectUi = {
  base: 'min-h-[44px] sm:min-h-[36px] justify-start text-left',
  content: 'w-(--reka-combobox-trigger-width)',
};

const inlineSelectUi = {
  base: 'justify-start text-left',
  content: 'w-(--reka-combobox-trigger-width)',
};

const containerClass = computed(() => {
  if (props.layout === 'inline') {
    return 'flex items-center gap-1.5';
  }
  if (props.layout === 'row') {
    return 'grid grid-cols-1 sm:grid-cols-2 gap-3';
  }
  return 'flex flex-col gap-3';
});
</script>

<template>
  <div :class="containerClass">
    <!-- Selector Kecamatan -->
    <UFormField
      v-if="layout !== 'inline'"
      :label="showLabels ? 'Kecamatan' : undefined"
      :required="required"
    >
      <div class="flex items-center gap-1.5 min-w-0">
        <USelectMenu
          :model-value="selectedKecamatanValue"
          :items="kecamatanItems"
          value-key="id"
          label-key="label"
          :loading="loadingKecamatan"
          :disabled="isKecamatanLocked"
          :leading-icon="isKecamatanLocked ? 'i-lucide-lock' : 'i-lucide-map-pin'"
          :highlight="isKecamatanActive"
          :color="isKecamatanActive ? 'primary' : undefined"
          :search-input="{ placeholder: 'Cari kecamatan...' }"
          :size="size"
          :content="dropdownContentProps"
          :ui="formSelectUi"
          placeholder="Pilih Kecamatan"
          aria-label="Pilih Kecamatan"
          class="w-full flex-1 min-w-0"
          @update:model-value="handleKecamatanSelect"
        />
        <UButton
          v-if="allowAll && showClear && isKecamatanActive"
          icon="i-lucide-x"
          color="neutral"
          variant="soft"
          :size="size"
          aria-label="Reset filter kecamatan"
          title="Reset ke semua kecamatan"
          class="shrink-0 cursor-pointer min-h-[44px] min-w-[44px] sm:min-h-[36px] sm:min-w-[36px] flex items-center justify-center"
          @click="handleKecamatanSelect(ALL_OPTION_ID)"
        />
      </div>
    </UFormField>

    <div v-else class="flex items-center gap-1">
      <USelectMenu
        :model-value="selectedKecamatanValue"
        :items="kecamatanItems"
        value-key="id"
        label-key="label"
        :loading="loadingKecamatan"
        :disabled="isKecamatanLocked"
        :leading-icon="isKecamatanLocked ? 'i-lucide-lock' : 'i-lucide-map-pin'"
        :highlight="isKecamatanActive"
        :color="isKecamatanActive ? 'primary' : undefined"
        :search-input="{ placeholder: 'Cari kecamatan...' }"
        :size="size"
        :content="dropdownContentProps"
        :ui="inlineSelectUi"
        placeholder="Kecamatan"
        aria-label="Filter Kecamatan"
        class="w-[156px] lg:w-44 xl:w-48"
        @update:model-value="handleKecamatanSelect"
      />
      <UButton
        v-if="allowAll && showClear && isKecamatanActive"
        icon="i-lucide-x"
        color="neutral"
        variant="ghost"
        size="xs"
        aria-label="Reset filter kecamatan"
        title="Reset ke semua kecamatan"
        class="shrink-0 cursor-pointer"
        @click="handleKecamatanSelect(ALL_OPTION_ID)"
      />
    </div>

    <!-- Selector Desa -->
    <UFormField
      v-if="layout !== 'inline'"
      :label="showLabels ? 'Desa / Kelurahan' : undefined"
      :required="required"
    >
      <div class="flex items-center gap-1.5 min-w-0">
        <USelectMenu
          :model-value="selectedDesaValue"
          :items="desaItems"
          value-key="id"
          label-key="label"
          :loading="loadingDesa"
          :disabled="isDesaLocked || !modelKecamatan"
          :leading-icon="isDesaLocked ? 'i-lucide-lock' : 'i-lucide-home'"
          :highlight="isDesaActive"
          :color="isDesaActive ? 'primary' : undefined"
          :search-input="{ placeholder: 'Cari desa / kelurahan...' }"
          :size="size"
          :content="dropdownContentProps"
          :ui="formSelectUi"
          :placeholder="modelKecamatan ? 'Pilih Desa' : 'Pilih Kecamatan dulu'"
          aria-label="Pilih Desa atau Kelurahan"
          class="w-full flex-1 min-w-0"
          @update:model-value="handleDesaSelect"
        />
        <UButton
          v-if="allowAll && showClear && isDesaActive"
          icon="i-lucide-x"
          color="neutral"
          variant="soft"
          :size="size"
          aria-label="Reset filter desa"
          title="Reset ke semua desa"
          class="shrink-0 cursor-pointer min-h-[44px] min-w-[44px] sm:min-h-[36px] sm:min-w-[36px] flex items-center justify-center"
          @click="handleDesaSelect(ALL_OPTION_ID)"
        />
      </div>
    </UFormField>

    <div v-else class="flex items-center gap-1">
      <USelectMenu
        :model-value="selectedDesaValue"
        :items="desaItems"
        value-key="id"
        label-key="label"
        :loading="loadingDesa"
        :disabled="isDesaLocked || !modelKecamatan"
        :leading-icon="isDesaLocked ? 'i-lucide-lock' : 'i-lucide-home'"
        :highlight="isDesaActive"
        :color="isDesaActive ? 'primary' : undefined"
        :search-input="{ placeholder: 'Cari desa...' }"
        :size="size"
        :content="dropdownContentProps"
        :ui="inlineSelectUi"
        :placeholder="modelKecamatan ? 'Desa' : 'Semua Desa'"
        aria-label="Filter Desa"
        class="w-[156px] lg:w-44 xl:w-48"
        @update:model-value="handleDesaSelect"
      />
      <UButton
        v-if="allowAll && showClear && isDesaActive"
        icon="i-lucide-x"
        color="neutral"
        variant="ghost"
        size="xs"
        aria-label="Reset filter desa"
        title="Reset ke semua desa"
        class="shrink-0 cursor-pointer"
        @click="handleDesaSelect(ALL_OPTION_ID)"
      />
    </div>
  </div>
</template>

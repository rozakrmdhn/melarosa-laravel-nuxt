<script setup lang="ts">
import { useAuthStore } from '~/stores/auth';
import { useWilayahStore } from '~/stores/wilayah';

interface Props {
  modelKecamatan?: number | null;
  modelDesa?: number | null;
  disabled?: boolean;
  showLabels?: boolean;
  required?: boolean;
  layout?: 'row' | 'col';
  allOptionLabel?: string;
  allowAll?: boolean;
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
  size: 'sm',
});

const emit = defineEmits<{
  (e: 'update:modelKecamatan', val: number | null): void;
  (e: 'update:modelDesa', val: number | null): void;
  (e: 'change', val: { id_kecamatan: number | null; id_desa: number | null }): void;
}>();

const auth = useAuthStore();
const wilayahStore = useWilayahStore();

const rawKecamatanList = computed(() => wilayahStore.kecamatanList);
const rawDesaList = computed(() => {
  if (!props.modelKecamatan) return [];
  return wilayahStore.desaByKecamatan[Number(props.modelKecamatan)] || [];
});

const loadingKecamatan = computed(() => wilayahStore.loadingKecamatan);
const loadingDesa = computed(() => wilayahStore.isDesaLoading(props.modelKecamatan));

// Auto-lock logic berdasarkan role & wilayah pengguna
const isKecamatanLocked = computed(() => {
  if (props.disabled) return true;
  if (auth.hasRole('admin') || auth.hasRole('verifierBappeda')) return false;
  return !!auth.user?.id_kecamatan;
});

const isDesaLocked = computed(() => {
  if (props.disabled) return true;
  if (auth.hasRole('admin') || auth.hasRole('verifierBappeda')) return false;
  return !!auth.user?.id_desa;
});

const kecamatanItems = computed(() => {
  const items = rawKecamatanList.value.map((k) => ({
    id: k.id,
    label: k.nama_kecamatan,
  }));
  if (props.allowAll) {
    return [{ id: null as any, label: `${props.allOptionLabel} Kecamatan` }, ...items];
  }
  return items;
});

const desaItems = computed(() => {
  const items = rawDesaList.value.map((d) => ({
    id: d.id,
    label: d.nama_desa,
  }));
  if (props.allowAll) {
    return [{ id: null as any, label: `${props.allOptionLabel} Desa` }, ...items];
  }
  return items;
});

function resolveId(val: any): number | null {
  if (val === null || val === undefined) return null;
  if (typeof val === 'object') {
    val = val.id ?? val.value ?? null;
  }
  if (val === null || val === undefined || val === '' || val === 'ALL') return null;
  const n = Number(val);
  return isNaN(n) ? null : n;
}

function handleKecamatanSelect(val: any) {
  const newKec = resolveId(val);
  emit('update:modelKecamatan', newKec);
  if (!isDesaLocked.value) {
    emit('update:modelDesa', null);
    emit('change', { id_kecamatan: newKec, id_desa: null });
  } else {
    emit('change', { id_kecamatan: newKec, id_desa: props.modelDesa });
  }
}

function handleDesaSelect(val: any) {
  const newDesa = resolveId(val);
  emit('update:modelDesa', newDesa);
  emit('change', { id_kecamatan: props.modelKecamatan, id_desa: newDesa });
}

async function loadKecamatan() {
  await wilayahStore.getKecamatanList();
  if (auth.user?.id_kecamatan && !props.modelKecamatan) {
    emit('update:modelKecamatan', auth.user.id_kecamatan);
  }
}

async function loadDesa(kecId?: number | null) {
  if (!kecId) return;
  await wilayahStore.getDesaList(kecId);
  if (auth.user?.id_desa && !props.modelDesa) {
    emit('update:modelDesa', auth.user.id_desa);
  }
}

watch(
  () => props.modelKecamatan,
  (newKec) => {
    loadDesa(newKec);
  },
  { immediate: true }
);

onMounted(() => {
  loadKecamatan();
});
</script>

<template>
  <div :class="layout === 'row' ? 'grid grid-cols-1 md:grid-cols-2 gap-3' : 'flex flex-col gap-3'">
    <!-- Selector Kecamatan -->
    <UFormField :label="showLabels ? 'Kecamatan' : undefined" :required="required">
      <USelectMenu
        :model-value="modelKecamatan"
        :items="kecamatanItems"
        value-key="id"
        label-key="label"
        :loading="loadingKecamatan"
        :disabled="isKecamatanLocked"
        :trailing-icon="isKecamatanLocked ? 'i-lucide-lock' : undefined"
        :size="size"
        placeholder="Pilih Kecamatan"
        class="w-full"
        @update:model-value="handleKecamatanSelect"
      />
    </UFormField>

    <!-- Selector Desa -->
    <UFormField :label="showLabels ? 'Desa / Kelurahan' : undefined" :required="required">
      <USelectMenu
        :model-value="modelDesa"
        :items="desaItems"
        value-key="id"
        label-key="label"
        :loading="loadingDesa"
        :disabled="isDesaLocked || !modelKecamatan"
        :trailing-icon="isDesaLocked ? 'i-lucide-lock' : undefined"
        :size="size"
        :placeholder="modelKecamatan ? 'Pilih Desa' : 'Pilih Kecamatan dulu'"
        class="w-full"
        @update:model-value="handleDesaSelect"
      />
    </UFormField>
  </div>
</template>

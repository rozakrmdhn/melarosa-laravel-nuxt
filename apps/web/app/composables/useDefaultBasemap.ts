import { onMounted, ref, watch, type Ref } from 'vue';
import { useBasemapStore } from '~/stores/basemap';
import type { BasemapEngine } from '~/types/basemap';

/**
 * Mengambil default basemap aktif dari pengaturan /admin/basemaps (basemapStore)
 * dan menyinkronkannya ke halaman peta sampai pengguna memilih basemap lain secara manual.
 */
export function useDefaultBasemap<T extends string = string>(
  engine: BasemapEngine = 'openlayers',
  onApplied?: (code: T) => void,
) {
  const basemapStore = useBasemapStore();
  const userSelected = ref(false);
  let isApplyingDefault = false;

  const resolveDefaultCode = (): T => {
    const raw = basemapStore.defaultCode || 'esri';
    const matched = basemapStore.getByCode(raw, engine);
    return (matched?.kode || raw) as T;
  };

  const currentBasemap = ref<T>(resolveDefaultCode()) as Ref<T>;

  function applyDefaultIfUnset() {
    if (userSelected.value) return;
    const resolved = resolveDefaultCode();
    if (currentBasemap.value !== resolved) {
      isApplyingDefault = true;
      currentBasemap.value = resolved;
      onApplied?.(resolved);
      queueMicrotask(() => {
        isApplyingDefault = false;
      });
    } else {
      onApplied?.(resolved);
    }
  }

  function setBasemapChoice(code: T) {
    if (!isApplyingDefault) {
      userSelected.value = true;
    }
    currentBasemap.value = code;
    onApplied?.(code);
  }

  onMounted(async () => {
    await basemapStore.ensureLoaded();
    applyDefaultIfUnset();
  });

  watch(
    () => [basemapStore.defaultCode, basemapStore.loaded],
    () => {
      applyDefaultIfUnset();
    },
  );

  return {
    basemapStore,
    currentBasemap,
    userSelected,
    setBasemapChoice,
  };
}

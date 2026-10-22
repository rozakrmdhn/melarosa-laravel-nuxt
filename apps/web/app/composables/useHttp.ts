import type { $Fetch } from 'nitropack/types';
import type { HttpUseFetchOptions } from '~~';

export function useHttp<T>(
  url: string | (() => string),
  options?: HttpUseFetchOptions<T>,
) {
  const auth = useAuthStore();
  const userScope = computed(() => auth.user?.uuid || 'anon');

  const rawKey = options?.key;
  const key = computed(() => {
    let base = typeof rawKey === 'function' ? rawKey() : rawKey;
    if (!base) {
      const urlStr = typeof url === 'function' ? url() : url;
      let queryStr = '';
      if (options?.query) {
        const q = unref(options.query);
        if (q && typeof q === 'object') {
          try {
            queryStr = ':' + Object.entries(q)
              .filter(([_, v]) => v !== undefined && v !== null && v !== '')
              .map(([k, v]) => `${k}=${unref(v)}`)
              .sort()
              .join('&');
          } catch {
            queryStr = '';
          }
        }
      }
      base = `${urlStr}${queryStr}`;
    }
    return `${base}:${userScope.value}`;
  });

  return useFetch<T>(url, {
    ...options,
    key: key.value,
    $fetch: $http as $Fetch,
  } as any);
}
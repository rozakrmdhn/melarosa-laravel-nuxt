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
    const base = typeof rawKey === 'function'
      ? rawKey()
      : (rawKey || (typeof url === 'function' ? url() : url));
    return `${base}:${userScope.value}`;
  });

  return useFetch<T>(url, {
    ...options,
    key: key.value,
    $fetch: $http as $Fetch,
  } as any);
}
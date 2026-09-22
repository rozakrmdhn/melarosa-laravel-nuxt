import { useAuthStore } from '~/stores/auth';

export function usePermission() {
  const auth = useAuthStore();

  function isAdmin(): boolean {
    return (auth.user?.roles ?? []).includes('admin');
  }

  function can(permission: string): boolean {
    if (isAdmin()) return true;
    return (auth.user?.permissions ?? []).includes(permission);
  }

  function canAny(permissions: string[]): boolean {
    if (isAdmin()) return true;
    return permissions.some((p) => (auth.user?.permissions ?? []).includes(p));
  }

  function canAll(permissions: string[]): boolean {
    if (isAdmin()) return true;
    return permissions.every((p) => (auth.user?.permissions ?? []).includes(p));
  }

  return {
    isAdmin,
    can,
    canAny,
    canAll,
  };
}

import { defineStore } from 'pinia'

export interface NavItem {
  label: string;
  icon: string;
  to?: string;
  exact?: boolean;
  children?: NavItem[];
}

export type User = {
  id?: number;
  uuid: string;
  name: string;
  email: string;
  avatar: string;
  id_kecamatan?: number | null;
  id_desa?: number | null;
  status?: boolean;
  kecamatan?: { id: number; nama_kecamatan: string } | null;
  desa?: { id: number; nama_desa: string } | null;
  must_verify_email: boolean;
  has_password: boolean;
  roles: string[];
  permissions: string[];
  providers: string[];
  navigation?: NavItem[];
}

export const useAuthStore = defineStore('auth', () => {
  const config = useRuntimeConfig();

  const loggedCookie = useCookie('logged', {
    path: '/',
    sameSite: 'strict',
    secure: config.public.apiBase.startsWith('https://'),
    maxAge: 60 * 60 * 24 * 365
  });

  const user = ref(<User>{});

  async function logout(): Promise<void> {
    const toast = useToast();
    try {
      await $http('logout', { method: 'POST' });
    } catch {
      // Ignore network errors on session termination
    }
    reset();
    toast.add({
      icon: 'i-lucide-check-circle',
      title: 'Berhasil Keluar',
      description: 'Anda telah berhasil logout dari sistem.',
      color: 'success',
    });
    await navigateTo('/auth/login');
  }

  async function fetchUser(): Promise<void> {
    try {
      const res = await $http<{ user: User }>('user');
      user.value = res.user;
    } catch {
      // ...
    }
  }

  function fetchCsrf(): Promise<unknown> {
    return $http('/sanctum/csrf-cookie');
  }

  async function login(): Promise<void> {
    loggedCookie.value = '1';
    await fetchUser();
  }

  function reset(): void {
    loggedCookie.value = null;
    user.value = <User>{}
  }

  function hasRole(name: string): boolean {
    return (user.value.roles ?? []).includes(name);
  }

  function hasPermission(name: string): boolean {
    return (user.value.permissions ?? []).includes(name);
  }

  return {
    user,
    logged: loggedCookie,
    login,
    logout,
    fetchUser,
    fetchCsrf,
    reset,
    hasRole,
    hasPermission,
  }
})

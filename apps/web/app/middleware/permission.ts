export default defineNuxtRouteMiddleware((to) => {
  const { can, canAny, isAdmin } = usePermission();

  const required = to.meta.permission as string | string[] | undefined;

  if (!required) return;

  const allowed = Array.isArray(required) ? canAny(required) : can(required);

  if (!allowed && !isAdmin()) {
    const toast = useToast();
    toast.add({
      icon: 'i-heroicons-lock-closed',
      title: 'Akses ditolak.',
      description: 'Kamu tidak memiliki izin untuk mengakses halaman ini.',
      color: 'error',
    });
    return navigateTo('/admin');
  }
});

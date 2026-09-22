export default defineNuxtRouteMiddleware((to, from) => {
  const auth = useAuthStore()

  const hasAccess = auth.hasRole('admin') || (auth.user?.permissions?.length ?? 0) > 0

  if (!auth.logged || !hasAccess) {
    const toast = useToast()

    toast.add({
      icon: "i-heroicons-exclamation-circle-solid",
      title: "Access denied.",
      color: "error",
    });

    return false;
  }
})

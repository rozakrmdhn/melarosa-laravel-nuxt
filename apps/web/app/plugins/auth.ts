export default defineNuxtPlugin(async (nuxtApp) => {
  const auth = useAuthStore();

  if (import.meta.client) {
    try {
      await auth.fetchCsrf();
    } catch (error) {
      console.warn('Gagal memuat cookie CSRF:', error);
    }
  }

  if (auth.logged && !auth.user?.uuid) {
    await auth.fetchUser();
  }
})

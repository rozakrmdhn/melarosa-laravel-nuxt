export default defineNuxtPlugin(async (nuxtApp) => {
  const auth = useAuthStore();
  const xsrf = useCookie('XSRF-TOKEN');

  if (import.meta.client && !xsrf.value) {
    try {
      await auth.fetchCsrf();
    } catch (error) {
      console.warn('Gagal memuat cookie CSRF:', error);
    }
  }

  if (auth.logged && !auth.user?.uuid) {
    try {
      await auth.fetchUser();
    } catch (error) {
      console.warn('Gagal memuat profil pengguna:', error);
    }
  }
})

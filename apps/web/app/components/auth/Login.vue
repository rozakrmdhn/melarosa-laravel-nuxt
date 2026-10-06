<script lang="ts" setup>
import type { Form, ButtonProps } from "#ui/types";
import type { AuthProviders } from "~~";

const config = useRuntimeConfig();
const router = useRouter();
const auth = useAuthStore();
const form = useTemplateRef<Form<any>>('form');
const toast = useToast();

const state = reactive({
  email: "",
  password: "",
  remember: false,
});

const submitting = ref(false);

async function onSubmit(): Promise<void> {
  submitting.value = true;

  try {
    const res = await $http<{ ok?: boolean; message?: string }>("login", {
      method: "POST",
      body: { ...state },
    });

    if (res?.ok) {
      await auth.login();
      toast.add({
        icon: "i-lucide-check-circle",
        title: "Berhasil Masuk",
        description: res.message || `Selamat datang kembali${auth.user?.name ? ', ' + auth.user.name : ''}!`,
        color: "success",
      });
      await router.push("/admin");
    }
  } catch (err: any) {
    if (err?.response?.status === 422) {
      form.value?.setErrors(err.response?._data?.errors ?? []);
    } else {
      const msg = err?.data?.message || err?.response?._data?.message || "Login gagal. Silakan periksa kembali email dan password Anda.";
      toast.add({
        icon: "i-lucide-alert-circle",
        title: "Gagal Masuk",
        description: msg,
        color: "error",
      });
    }
  } finally {
    submitting.value = false;
  }
}

const providers = ref<AuthProviders>(config.public.providers);

async function handleMessage(event: { data: any }): Promise<void> {
  const provider = event.data.provider as string;

  if (Object.keys(providers.value).includes(provider)) {
    providers.value[provider].loading = false;

    await auth.login();
    toast.add({
      icon: "i-lucide-check-circle",
      title: "Berhasil Masuk",
      description: `Selamat datang kembali${auth.user?.name ? ', ' + auth.user.name : ''}!`,
      color: "success",
    });
    await router.push("/admin");
  } else if (event.data.message) {
    toast.add({
      icon: "i-heroicons-exclamation-circle-solid",
      color: "error",
      title: event.data.message,
    });
  }
}

function loginVia(provider: string): void {
  providers.value[provider].loading = true;

  const width = 640;
  const height = 660;
  const left = window.screen.width / 2 - width / 2;
  const top = window.screen.height / 2 - height / 2;

  const popup = window.open(
    `${config.public.apiBase}${config.public.apiPrefix}/login/${provider}/redirect`,
    "Sign In",
    `toolbar=no, location=no, directories=no, status=no, menubar=no, scollbars=no, resizable=no, copyhistory=no, width=${width},height=${height},top=${top},left=${left}`
  );

  const interval = setInterval(() => {
    if (!popup || popup.closed) {
      clearInterval(interval);
      providers.value[provider].loading = false;
    }
  }, 500);
}

onMounted(() => window.addEventListener("message", handleMessage));
onBeforeUnmount(() => window.removeEventListener("message", handleMessage));
const showPassword = ref(false);
</script>

<template>
  <div class="space-y-4">
    <!-- Form Login Utama -->
    <UForm ref="form" :state="state" class="space-y-4" @submit="onSubmit">
      <UFormField label="Alamat Email" name="email" required size="sm">
        <UInput
          v-model="state.email"
          class="w-full"
          placeholder="nama@domain.com"
          icon="i-heroicons-envelope"
          type="email"
          autofocus
          size="md"
        />
      </UFormField>

      <UFormField label="Kata Sandi" name="password" required size="sm">
        <div class="relative w-full">
          <UInput
            v-model="state.password"
            :type="showPassword ? 'text' : 'password'"
            class="w-full"
            placeholder="Masukkan kata sandi"
            icon="i-heroicons-lock-closed"
            size="md"
          />
          <button
            type="button"
            tabindex="-1"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors"
            :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
            @click="showPassword = !showPassword"
          >
            <UIcon
              :name="showPassword ? 'i-heroicons-eye-slash' : 'i-heroicons-eye'"
              class="size-4"
            />
          </button>
        </div>
      </UFormField>

      <div class="flex items-center justify-between text-xs pt-0.5">
        <UCheckbox v-model="state.remember" label="Ingat saya" />
        <NuxtLink
          to="/auth/forgot"
          class="text-primary-600 dark:text-primary-400 hover:underline font-medium"
        >
          Lupa kata sandi?
        </NuxtLink>
      </div>

      <UButton
        type="submit"
        label="Masuk ke Sistem"
        icon="i-heroicons-arrow-right"
        trailing
        block
        size="md"
        color="primary"
        variant="solid"
        :loading="submitting"
        class="font-medium cursor-pointer shadow-sm mt-2"
      />
    </UForm>

    <!-- Social Providers / OAuth jika aktif -->
    <template v-if="providers && Object.keys(providers).length > 0">
      <USeparator label="atau masuk dengan" class="my-4 text-xs text-gray-400" />

      <div class="flex gap-2.5">
        <UButton
          v-for="(provider, key) in providers"
          :key="key"
          :loading="provider.loading"
          :icon="provider.icon"
          :color="provider.color as ButtonProps['color']"
          :variant="provider.variant as ButtonProps['variant']"
          :label="provider.name"
          size="sm"
          class="w-full flex items-center justify-center font-normal"
          @click="loginVia(key as string)"
        />
      </div>
    </template>

    <div class="text-xs text-center text-gray-500 dark:text-gray-400 pt-2">
      Belum memiliki akun akses?
      <NuxtLink to="/auth/register" class="text-primary-600 dark:text-primary-400 font-medium hover:underline ml-1">
        Daftar sekarang
      </NuxtLink>
    </div>
  </div>
</template>

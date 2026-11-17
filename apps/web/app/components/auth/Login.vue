<script lang="ts" setup>
import type { Form, FormError, ButtonProps } from "#ui/types";
import type { AuthProviders } from "~~";

const config = useRuntimeConfig();
const router = useRouter();
const auth = useAuthStore();
const form = useTemplateRef<Form<typeof state>>("form");
const toast = useToast();

const state = reactive({
  email: "",
  password: "",
  remember: false,
});

const submitting = ref(false);
const showPassword = ref(false);
const isCapsLockOn = ref(false);
const formError = ref<string | null>(null);
const isHelpOpen = ref(false);

function clearFeedbackError(): void {
  if (formError.value) {
    formError.value = null;
  }
}

watch(
  () => [state.email, state.password],
  () => {
    clearFeedbackError();
  }
);

function validate(formState: typeof state): FormError[] {
  const errors: FormError[] = [];
  const trimmedEmail = formState.email.trim();

  if (!trimmedEmail) {
    errors.push({
      name: "email",
      message: "Alamat email wajib diisi.",
    });
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmedEmail)) {
    errors.push({
      name: "email",
      message: "Format alamat email belum valid.",
    });
  }

  if (!formState.password) {
    errors.push({
      name: "password",
      message: "Kata sandi wajib diisi.",
    });
  }

  return errors;
}

function handlePasswordKey(event: KeyboardEvent): void {
  if (typeof event.getModifierState === "function") {
    isCapsLockOn.value = event.getModifierState("CapsLock");
  }
}

async function onSubmit(): Promise<void> {
  submitting.value = true;
  formError.value = null;

  try {
    const res = await $http<{ ok?: boolean; message?: string }>("login", {
      method: "POST",
      body: {
        email: state.email.trim(),
        password: state.password,
        remember: state.remember,
      },
    });

    if (res?.ok) {
      await auth.login();
      toast.add({
        icon: "i-lucide-check-circle-2",
        title: "Berhasil Masuk",
        description:
          res.message ||
          `Selamat datang kembali${auth.user?.name ? ", " + auth.user.name : ""}.`,
        color: "success",
      });
      await router.push("/admin");
    } else {
      formError.value =
        res?.message ||
        "Autentikasi belum berhasil. Silakan periksa kembali kredensial akun Anda.";
    }
  } catch (err: any) {
    if (err?.response?.status === 422) {
      form.value?.setErrors(err.response?._data?.errors ?? []);
      formError.value =
        err?.response?._data?.message ||
        "Data login belum sesuai. Periksa kolom email dan kata sandi.";
    } else {
      const msg =
        err?.data?.message ||
        err?.response?._data?.message ||
        "Tidak dapat memverifikasi akun ke server saat ini. Pastikan email dan kata sandi benar atau coba beberapa saat lagi.";
      formError.value = msg;
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
  const provider = event.data?.provider as string;

  if (provider && Object.keys(providers.value).includes(provider)) {
    providers.value[provider].loading = false;

    await auth.login();
    toast.add({
      icon: "i-lucide-check-circle-2",
      title: "Berhasil Masuk",
      description: `Selamat datang kembali${auth.user?.name ? ", " + auth.user.name : ""}.`,
      color: "success",
    });
    await router.push("/admin");
  } else if (event.data?.message) {
    formError.value = event.data.message;
    toast.add({
      icon: "i-lucide-alert-circle",
      color: "error",
      title: event.data.message,
    });
  }
}

function loginVia(provider: string): void {
  if (!providers.value[provider]) return;
  providers.value[provider].loading = true;
  formError.value = null;

  const width = 640;
  const height = 660;
  const left = window.screen.width / 2 - width / 2;
  const top = window.screen.height / 2 - height / 2;

  const popup = window.open(
    `${config.public.apiBase}${config.public.apiPrefix}/login/${provider}/redirect`,
    "Sign In",
    `toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=no, resizable=no, copyhistory=no, width=${width},height=${height},top=${top},left=${left}`
  );

  const interval = setInterval(() => {
    if (!popup || popup.closed) {
      clearInterval(interval);
      if (providers.value[provider]) {
        providers.value[provider].loading = false;
      }
    }
  }, 500);
}

onMounted(() => window.addEventListener("message", handleMessage));
onBeforeUnmount(() => window.removeEventListener("message", handleMessage));
</script>

<template>
  <div class="space-y-5">
    <div
      v-if="formError"
      role="alert"
      aria-live="polite"
      class="flex items-start gap-3 rounded-xl border border-[#FCA5A5] dark:border-red-500/40 bg-[#FEF2F2] dark:bg-[#450A0A] px-4 py-3.5 text-sm text-[#991B1B] dark:text-[#FCA5A5]"
    >
      <UIcon
        name="i-lucide-alert-circle"
        class="size-5 shrink-0 mt-0.5"
        aria-hidden="true"
      />
      <div class="flex-1 min-w-0 leading-relaxed">
        <p class="font-semibold">Verifikasi Masuk Gagal</p>
        <p class="mt-0.5 text-xs sm:text-sm">{{ formError }}</p>
      </div>
      <button
        type="button"
        class="min-h-[44px] min-w-[44px] -my-2 -mr-2 rounded-lg inline-flex items-center justify-center hover:bg-black/5 dark:hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB] cursor-pointer shrink-0"
        aria-label="Tutup pesan kesalahan"
        @click="formError = null"
      >
        <UIcon name="i-lucide-x" class="size-4" aria-hidden="true" />
      </button>
    </div>

    <UForm
      ref="form"
      :state="state"
      :validate="validate"
      class="space-y-5"
      novalidate
      @submit="onSubmit"
    >
      <UFormField
        label="Alamat Email"
        name="email"
        required
        :ui="{
          label: 'text-sm font-semibold text-[#17251F] dark:text-slate-100',
          error: 'mt-1.5 text-xs font-medium text-[#991B1B] dark:text-[#FCA5A5]',
        }"
      >
        <UInput
          id="login-email"
          v-model="state.email"
          type="email"
          inputmode="email"
          autocomplete="email"
          placeholder="email@instansi.go.id"
          icon="i-lucide-mail"
          size="lg"
          class="w-full"
          :ui="{
            base: 'min-h-[48px] rounded-xl bg-white dark:bg-[#08101c] border-0 ring-1 ring-[#839188] dark:ring-[#64748B] focus-visible:ring-2 focus-visible:ring-[#2563EB] text-[#17251F] dark:text-[#F8FAFC] placeholder:text-[#4E5D55] dark:placeholder:text-[#94A3B8]',
            leadingIcon: 'text-[#4E5D55] dark:text-[#94A3B8]',
          }"
        />
      </UFormField>

      <UFormField
        label="Kata Sandi"
        name="password"
        required
        :ui="{
          label: 'text-sm font-semibold text-[#17251F] dark:text-slate-100',
          error: 'mt-1.5 text-xs font-medium text-[#991B1B] dark:text-[#FCA5A5]',
        }"
      >
        <div class="relative w-full">
          <UInput
            id="login-password"
            v-model="state.password"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="current-password"
            placeholder="Masukkan kata sandi"
            icon="i-lucide-lock"
            size="lg"
            class="w-full"
            :ui="{
              base: 'min-h-[48px] pr-13 rounded-xl bg-white dark:bg-[#08101c] border-0 ring-1 ring-[#839188] dark:ring-[#64748B] focus-visible:ring-2 focus-visible:ring-[#2563EB] text-[#17251F] dark:text-[#F8FAFC] placeholder:text-[#4E5D55] dark:placeholder:text-[#94A3B8]',
              leadingIcon: 'text-[#4E5D55] dark:text-[#94A3B8]',
            }"
            @keydown="handlePasswordKey"
            @keyup="handlePasswordKey"
            @blur="isCapsLockOn = false"
          />
          <button
            type="button"
            class="absolute right-1 top-1/2 -translate-y-1/2 min-h-[44px] min-w-[44px] rounded-lg inline-flex items-center justify-center text-[#4E5D55] dark:text-[#CBD5E1] hover:text-[#17251F] dark:hover:text-white hover:bg-[#F7F8F5] dark:hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB] transition-colors cursor-pointer"
            :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
            :aria-pressed="showPassword"
            @click="showPassword = !showPassword"
          >
            <UIcon
              :name="showPassword ? 'i-lucide-eye-off' : 'i-lucide-eye'"
              class="size-4.5"
              aria-hidden="true"
            />
          </button>
        </div>

        <p
          v-if="isCapsLockOn"
          role="status"
          class="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-amber-300 dark:border-amber-500/40 bg-[#FFFBEB] dark:bg-[#451A03] px-2.5 py-1 text-xs font-medium text-[#92400E] dark:text-[#FDE68A]"
        >
          <UIcon name="i-lucide-alert-triangle" class="size-3.5 shrink-0" aria-hidden="true" />
          <span>Tombol Caps Lock sedang aktif.</span>
        </p>
      </UFormField>

      <div class="flex flex-wrap items-center justify-between gap-2">
        <label
          for="login-remember"
          class="inline-flex items-center gap-2.5 min-h-[44px] pr-2 text-sm text-[#17251F] dark:text-slate-200 cursor-pointer select-none"
        >
          <input
            id="login-remember"
            v-model="state.remember"
            type="checkbox"
            class="size-4.5 rounded border-[#839188] dark:border-[#64748B] text-[#2563EB] focus:ring-2 focus:ring-[#2563EB] focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-[#0c1522] cursor-pointer"
          />
          <span>Ingat sesi di perangkat ini</span>
        </label>

        <NuxtLink
          to="/auth/forgot"
          class="inline-flex items-center min-h-[44px] px-2 -mr-2 rounded-lg text-sm font-semibold text-[#1D4ED8] dark:text-[#93C5FD] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB]"
        >
          Lupa kata sandi?
        </NuxtLink>
      </div>

      <button
        type="submit"
        :disabled="submitting"
        class="w-full min-h-[48px] px-5 py-3 rounded-xl bg-[#2563EB] hover:bg-[#1D4ED8] active:bg-[#1E40AF] disabled:opacity-65 disabled:cursor-not-allowed text-white font-semibold text-sm sm:text-base transition-colors flex items-center justify-center gap-2.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB] focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-[#0c1522] cursor-pointer"
      >
        <UIcon
          v-if="submitting"
          name="i-lucide-loader-2"
          class="size-4.5 animate-spin motion-reduce:animate-none"
          aria-hidden="true"
        />
        <span>{{ submitting ? "Memverifikasi Akun..." : "Masuk ke Portal" }}</span>
      </button>
    </UForm>

    <template v-if="providers && Object.keys(providers).length > 0">
      <div class="relative flex items-center py-0.5">
        <div class="flex-1 border-t border-[#DFE5DE] dark:border-white/10" />
        <span class="px-3 text-xs font-medium text-[#4E5D55] dark:text-[#94A3B8]">
          Atau masuk melalui SSO
        </span>
        <div class="flex-1 border-t border-[#DFE5DE] dark:border-white/10" />
      </div>

      <div
        class="grid gap-2.5"
        :class="
          Object.keys(providers).length === 1
            ? 'grid-cols-1'
            : 'grid-cols-1 sm:grid-cols-2'
        "
      >
        <UButton
          v-for="(provider, key) in providers"
          :key="key"
          :loading="provider.loading"
          :icon="provider.icon || 'i-lucide-shield-check'"
          :color="provider.color as ButtonProps['color']"
          :variant="(provider.variant as ButtonProps['variant']) || 'outline'"
          :label="`Masuk dengan ${provider.name}`"
          size="md"
          class="w-full min-h-[46px] flex items-center justify-center font-semibold rounded-xl border border-[#B8C4BC] dark:border-[#64748B] bg-white dark:bg-[#08101c] text-[#17251F] dark:text-[#F8FAFC] hover:bg-[#F7F8F5] dark:hover:bg-white/5 cursor-pointer"
          @click="loginVia(key as string)"
        />
      </div>
    </template>

    <div class="rounded-xl border border-[#DFE5DE] dark:border-white/10 bg-[#F7F8F5] dark:bg-white/[0.03] overflow-hidden">
      <button
        type="button"
        class="w-full min-h-[46px] px-4 py-3 flex items-center justify-between gap-3 text-left text-xs sm:text-sm font-semibold text-[#17251F] dark:text-slate-100 hover:bg-[#EFF2ED] dark:hover:bg-white/[0.06] transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#2563EB]"
        :aria-expanded="isHelpOpen"
        aria-controls="login-access-help"
        @click="isHelpOpen = !isHelpOpen"
      >
        <span class="flex items-center gap-2 min-w-0">
          <UIcon
            name="i-lucide-help-circle"
            class="size-4 text-[#176B4D] dark:text-[#34D399] shrink-0"
            aria-hidden="true"
          />
          <span>Belum memiliki akun atau butuh aktivasi?</span>
        </span>
        <UIcon
          :name="isHelpOpen ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
          class="size-4 text-[#4E5D55] dark:text-[#94A3B8] shrink-0"
          aria-hidden="true"
        />
      </button>

      <div
        v-if="isHelpOpen"
        id="login-access-help"
        class="px-4 pb-3.5 pt-2 border-t border-[#DFE5DE] dark:border-white/10 text-xs sm:text-sm text-[#4E5D55] dark:text-[#CBD5E1] space-y-2 leading-relaxed"
      >
        <p>
          Akun pengelola diterbitkan oleh Administrator Kabupaten untuk operator OPD dan Pemerintah Kecamatan di Kabupaten Bojonegoro.
        </p>
        <p>
          Untuk melihat peta wilayah, jaringan jalan, dan model kota tanpa login, buka
          <NuxtLink
            to="/maps"
            class="font-semibold text-[#1D4ED8] dark:text-[#93C5FD] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2563EB] rounded"
          >
            halaman Peta Interaktif Publik
          </NuxtLink>.
        </p>
      </div>
    </div>
  </div>
</template>

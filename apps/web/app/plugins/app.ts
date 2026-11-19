import type { NitroFetchRequest } from 'nitropack/types';
import type { HttpFetchOptions, HttpFetchContext } from '~~';

export default defineNuxtPlugin((nuxtApp) => {
  const toast = useToast();
  const config = useRuntimeConfig();
  const requestUrl = useRequestURL();
  const requestHeaders = useRequestHeaders(['cookie', 'x-forwarded-for', 'user-agent']);
  const xsrf = useCookie('XSRF-TOKEN');
  const auth = useAuthStore();

  function readLiveXsrfToken(): string | undefined {
    if (import.meta.client && typeof document !== 'undefined') {
      const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
      if (match?.[1]) {
        try {
          return decodeURIComponent(match[1]);
        } catch {
          return match[1];
        }
      }
    }
    return xsrf.value ?? undefined;
  }

  function buildHeaders(headers: any): Headers {
    const authHeaders: Record<string, string> = {};
    const liveToken = readLiveXsrfToken();
    if (liveToken) {
      authHeaders['X-XSRF-TOKEN'] = liveToken;
    }

    return {
      Accept: 'application/json',
      ...headers,
      ...authHeaders,
      ...(
        import.meta.server
          ? {
            referer: requestUrl.toString(),
            ...requestHeaders
          }
          : {}
      )
    };
  }

  function buildBaseURL(baseURL: string, path: string = ''): string {
    const cleanPath = path.startsWith('/') ? path : `/${path}`;
    const isSanctum = cleanPath.startsWith('/sanctum');

    const isRemote = typeof window !== 'undefined'
      && window.location.hostname !== 'localhost'
      && window.location.hostname !== '127.0.0.1';

    if (baseURL) {
      if (isRemote && (baseURL.includes('localhost') || baseURL.includes('127.0.0.1'))) {
        return isSanctum ? '' : config.public.apiPrefix;
      }
      return baseURL;
    }

    if (import.meta.server) {
      const serverBase = config.apiLocal || 'http://localhost:9000';
      return isSanctum ? serverBase : serverBase + config.public.apiPrefix;
    }

    // Jika diakses dari domain remote (misal Cloudflare Tunnel / non-localhost), gunakan relative path agar di-proxy oleh Nuxt Nitro
    if (isRemote) {
      return isSanctum ? '' : config.public.apiPrefix;
    }

    return isSanctum
      ? (config.public.apiBase || 'http://localhost:9000')
      : (config.public.apiBase + config.public.apiPrefix);
  }

  function buildSecureMethod(options: HttpFetchOptions): void {
    if (!import.meta.server && options.body instanceof FormData &&
      (options.method?.toLowerCase() === 'put')) {
      options.method = 'POST';
      options.body.append('_method', 'PUT');
    }
  }

  function isRequestWithAuth(baseURL: string, path: string): boolean {
    return (!baseURL || baseURL === config.public.apiBase || baseURL === config.apiLocal)
      && !path.startsWith('/_nuxt')
      && !path.startsWith('http://')
      && !path.startsWith('https://');
  }

  async function callHooks(context, hooks) {
    if (Array.isArray(hooks)) {
      for (const hook of hooks) {
        await hook(context);
      }
    } else if (hooks) {
      await hooks(context);
    }
  }

  const http = $fetch.create<unknown, NitroFetchRequest>(<HttpFetchOptions>{
    baseURL: '',
    retry: false,
    async onRequest(context: HttpFetchContext) {
      await callHooks(context, context.options.onFetch);

      if (!isRequestWithAuth(context.options.baseURL ?? '', context.request.toString())) return;

      context.options.credentials = 'include';
      context.options.baseURL = buildBaseURL(context.options.baseURL ?? '', context.request.toString());
      context.options.headers = buildHeaders(context.options.headers);

      buildSecureMethod(context.options);
    },
    async onRequestError(context: HttpFetchContext) {
      await callHooks(context, context.options.onFetchError);

      if (import.meta.server || context.error.name === 'AbortError') return;

      const message = context.error?.message?.trim() || 'Gagal menghubungkan ke server. Periksa koneksi internet Anda.';

      toast.add({
        icon: 'i-heroicons-exclamation-circle-solid',
        color: "error",
        title: message
      });
    },
    async onResponse(context: HttpFetchContext) {
      await callHooks(context, context.options.onFetchResponse);
    },
    async onResponseError(context: HttpFetchContext) {
      await callHooks(context, context.options.onFetchResponseError);

      if (context.response.status === 401) {
        const auth = useAuthStore();
        auth.reset();
      } else if (
        context.response.status !== 422
        && !(context.response.status === 419 && !(context.options as any)?._csrfRetried)
        && import.meta.client
      ) {
        const data = context.response._data;
        let message = '';

        if (typeof data === 'string' && data.trim()) {
          try {
            const parsed = JSON.parse(data);
            message = parsed.message || parsed.error || parsed.statusMessage || '';
          } catch {
            if (!data.trim().startsWith('<')) {
              message = data.trim();
            }
          }
        } else if (data && typeof data === 'object') {
          message = data.message || data.error || data.statusMessage || '';
        }

        if (!message && context.response.statusText?.trim()) {
          message = context.response.statusText.trim();
        }

        if (!message) {
          if (context.response.status === 403) {
            message = 'Akses ditolak: Anda tidak memiliki izin untuk tindakan ini (403).';
          } else if (context.response.status === 404) {
            message = 'Data atau endpoint tidak ditemukan (404).';
          } else if (context.response.status >= 500) {
            message = `Terjadi kesalahan pada server (Kode: ${context.response.status}).`;
          } else {
            message = `Terjadi kesalahan (Kode: ${context.response.status}).`;
          }
        }

        toast.add({
          icon: 'i-heroicons-exclamation-circle-solid',
          color: "error",
          title: message
        });
      }
    },
  });

  const httpWithCsrfRetry = Object.assign(
    async function wrappedHttp<T = unknown>(
      request: NitroFetchRequest,
      opts?: HttpFetchOptions & { _csrfRetried?: boolean },
    ): Promise<T> {
      try {
        return await (http as any)(request, opts);
      } catch (err: any) {
        const status = err?.response?.status ?? err?.statusCode;
        const reqStr = typeof request === 'string' ? request : String(request);
        const alreadyRetried = Boolean(opts?._csrfRetried);

        if (
          import.meta.client
          && status === 419
          && !alreadyRetried
          && !reqStr.includes('sanctum/csrf-cookie')
        ) {
          await (http as any)('/sanctum/csrf-cookie');
          const refreshed = readLiveXsrfToken();
          if (refreshed) {
            xsrf.value = refreshed;
          }
          return await (http as any)(request, {
            ...opts,
            _csrfRetried: true,
          });
        }

        throw err;
      }
    },
    http,
  );

  return {
    provide: {
      http: httpWithCsrfRetry as typeof http,
    }
  }
})
